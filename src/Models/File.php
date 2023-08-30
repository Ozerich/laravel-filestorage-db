<?php

namespace Ozerich\FileStorage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ozerich\FileStorage\Exceptions\InvalidFileForScenarioException;
use Ozerich\FileStorage\Exceptions\InvalidScenarioException;
use Ozerich\FileStorage\Exceptions\InvalidThumbnailException;
use Ozerich\FileStorage\Jobs\PrepareThumbnailsJob;
use Ozerich\FileStorage\Services\TempFile;
use Ozerich\FileStorage\Storage;
use Ozerich\FileStorage\Utils\FileNameHelper;

/**
 * Class File
 * @package Ozerich\FileStorage\Models
 *
 * @property int $id
 * @property string $uuid
 * @property string $scenario
 * @property string $hash
 * @property string $name
 * @property string $ext
 * @property string $mime
 * @property int $size
 * @property int $width
 * @property int $height
 * @property string $thumbnails
 */
class File extends Model
{
    use SoftDeletes;

    protected $table = 'files';

    private ?TempFile $tmpFile = null;

    protected static function boot()
    {
        parent::boot();

        static::deleted(function (self $file) {
            $file->deleteSelfFiles();
        });

        static::softDeleted(function (self $file) {
            $file->deleteSelfFiles();
        });
    }

    private function deleteSelfFiles()
    {
        try {
            $scenarioInstance = $this->scenarioInstance();
        } catch (InvalidScenarioException $exception) {
            return;
        }

        if ($scenarioInstance->shouldSaveFilesAfterDeletion()) {
            return;
        }

        Storage::staticDeleteThumbnails($this);

        $scenarioInstance->getStorage()->delete(
            FileNameHelper::get(
                $this->hash, $this->ext, null, false,
                $scenarioInstance->shouldSaveOriginalFilename() ? $this->name : null
            ),
            $this->hash
        );
    }

    /**
     * @return \Ozerich\FileStorage\Structures\Scenario
     * @throws InvalidScenarioException
     */
    public function scenarioInstance($throwException = true)
    {
        $scenario = Storage::getScenario($this->scenario);
        if (!$scenario && $throwException) {
            throw new InvalidScenarioException('Scenario "' . $this->scenario . '" not found');
        }
        return $scenario;
    }

    public function getBody(): ?string
    {
        try {
            $scenario = $this->scenarioInstance();
        } catch (InvalidScenarioException $exception) {
            return null;
        }

        return $scenario->getStorage()->getBody(
            FileNameHelper::get(
                $this->hash, $this->ext, null, false,
                $scenario->shouldSaveOriginalFilename() ? $this->name : null
            ),
            $this->hash
        );
    }

    public function setScenario($scenario, $regenerateThumbnails = false, $throwExceptionIfInvalid = false)
    {
        if ($this->scenario == $scenario) {
            return $this;
        }

        $scenarioInstance = $this->scenarioInstance();

        $filename = FileNameHelper::get($this->hash, $this->ext, null, false,
            $scenarioInstance->shouldSaveOriginalFilename() ? $this->name : null
        );

        $tmp = new TempFile();
        if (!$scenarioInstance->getStorage()->download($filename, $this->hash, $tmp->getPath())) {
            throw new \Exception('Can not download file - ' . $filename);
        }

        $scenarioInstance = Storage::getScenario($scenario, true);

        $validator = $scenarioInstance->getValidator();
        if ($validator) {
            $validate = $validator->validate($tmp->getPath(), $this->name);
            if (!$validate) {
                if ($throwExceptionIfInvalid) {
                    throw new InvalidFileForScenarioException($validator->getLastError());
                } else {
                    return $this;
                }
            }
        }

        $this->scenario = $scenarioInstance->getId();
        $this->save();

        $newFileName = FileNameHelper::get($this->hash, $this->ext, null, false,
            $scenarioInstance->shouldSaveOriginalFilename() ? $this->name : null
        );

        $scenarioInstance->getStorage()->upload(
            $tmp->getPath(),
            $newFileName,
            $this->hash,
        );

        if ($regenerateThumbnails && $scenarioInstance && $scenarioInstance->hasThumnbails()) {
            dispatch(new PrepareThumbnailsJob($this->id));
        }

        return $this;
    }

    public function getUrl($thumbnail_alias = null, $returnOriginalByDefault = true): ?string
    {
        try {
            $scenarioInstance = $this->scenarioInstance();
        } catch (InvalidScenarioException $exception) {
            return null;
        }

        $thumbnail = null;
        if (!empty($thumbnail_alias)) {
            try {
                $thumbnail = $scenarioInstance->getThumbnailByAlias($thumbnail_alias);
            } catch (InvalidThumbnailException $exception) {
                return null;
            }
        }

        $filename = FileNameHelper::get(
            $this->hash, $this->ext, $thumbnail, false,
            $scenarioInstance->shouldSaveOriginalFilename() ? $this->name : null
        );

        return $scenarioInstance->getStorage()->getUrl($filename, $this->hash);
    }

    private function dashesToCamelCase($string, $capitalizeFirstCharacter = false)
    {
        $str = str_replace('-', '', ucwords($string, '-'));

        if (!$capitalizeFirstCharacter) {
            $str = lcfirst($str);
        }

        return $str;
    }

    public function getDefaultThumbnailUrl($scenario = null)
    {
        if ($this->mime == 'image/svg' || $this->mime == 'image/svg+xml') {
            return $this->getUrl();
        }

        if ($scenario && $this->scenario != $scenario) {
            $this->setScenario($scenario);
        }

        $scenario = $this->scenarioInstance();
        if ($scenario->isSingleThumbnail()) {
            return $this->getUrl('default');
        } else {
            return $this->getUrl();
        }
    }

    public function getDefaultThumbnailJson($scenario = null)
    {
        return $this->getThumbnailJson('default', $scenario);
    }

    public function getThumbnailJson($thumbnailName)
    {
        if ($this->mime == 'image/svg' || $this->mime == 'image/svg+xml') {
            return [
                'url' => $this->getUrl()
            ];
        }

        $scenario = $this->scenarioInstance();

        try {
            $thumbnail = $scenario->getThumbnailByAlias($thumbnailName);
        } catch (InvalidThumbnailException $invalidThumbnailException) {
            return [
                'url' => $this->getUrl()
            ];
        }

        $originalFilename = $scenario->shouldSaveOriginalFilename() ? $this->name : null;

        $item = [
            'url' => $this->isThumbnailExists($thumbnail->getDatabaseValue(false, false)) ? $scenario->getStorage()->getUrl(
                FileNameHelper::get($this->hash, $this->ext, $thumbnail, false, $originalFilename),
                $this->hash,
            ) : $this->getUrl()
        ];

        if ($thumbnail->is2xSupport()) {
            $item['url_2x'] = $this->isThumbnailExists($thumbnail->getDatabaseValue(true, false)) ? $scenario->getStorage()->getUrl(
                FileNameHelper::get($this->hash, $this->ext, $thumbnail, true, $originalFilename),
                $this->hash,
            ) : null;
        }

        if ($thumbnail->isWebpSupport()) {
            $item['url_webp'] = $this->isThumbnailExists($thumbnail->getDatabaseValue(false, true)) ? $scenario->getStorage()->getUrl(
                FileNameHelper::get($this->hash, 'webp', $thumbnail, false, $originalFilename),
                $this->hash,
            ) : null;

            if ($thumbnail->is2xSupport()) {
                $item['url_webp_2x'] = $this->isThumbnailExists($thumbnail->getDatabaseValue(true, true)) ? $scenario->getStorage()->getUrl(
                    FileNameHelper::get($this->hash, 'webp', $thumbnail, true, $originalFilename),
                    $this->hash,
                ) : null;
            }
        }

        return $item;
    }

    public function getShortJson($thumbnailAlias = null, $replaceUrlWith = null)
    {
        return [
            'id' => $this->uuid,
            'mime' => $this->mime,
            'name' => $this->name,
            'size' => $this->size,
            'url' => $replaceUrlWith ?? $this->getUrl($thumbnailAlias)
        ];
    }

    public function getFullJson($ignoreThumbnails = [], $checkThumbnails = true)
    {
        try {
            $scenarioInstance = $this->scenarioInstance();
        } catch (InvalidScenarioException $exception) {
            return null;
        }

        $thumbs = [];

        if ($checkThumbnails) {
            Storage::checkThumbnails($this);
        }

        if ($scenarioInstance->hasThumnbails()) {
            $thumbnails = $scenarioInstance->getThumbnails();
            $thumbnailsFiltered = [];
            if (!empty($ignoreThumbnails)) {
                foreach ($thumbnails as $thumbnailAlias => $thumbnailInstance) {
                    if (in_array($thumbnailAlias, $ignoreThumbnails) == false) {
                        $thumbnailsFiltered[$thumbnailAlias] = $thumbnailInstance;
                    }
                }
            } else {
                $thumbnailsFiltered = $thumbnails;
            }

            $isSingleThumbnail = count($thumbnailsFiltered) === 1 && isset($thumbnailsFiltered['default']);
            if ($isSingleThumbnail) {
                $thumbs = $this->getThumbnailJson('default');
            } else {
                foreach ($thumbnailsFiltered as $alias => $thumbnail) {
                    $thumbs[$this->dashesToCamelCase($alias)] = $this->getThumbnailJson($alias);
                }
            }
        }

        if (empty($thumbs)) {
            return [
                'url' => $this->getUrl()
            ];
        } else {
            return $thumbs;
        }
    }

    public function exists($thumbnailAlias = null): bool
    {
        try {
            $scenarioInstance = $this->scenarioInstance();
        } catch (InvalidScenarioException $exception) {
            return false;
        }

        $thumbnail = null;
        if ($thumbnailAlias) {
            try {
                $thumbnail = $scenarioInstance->getThumbnailByAlias($thumbnailAlias);
            } catch (InvalidThumbnailException $exception) {
                return false;
            }
        }

        return $scenarioInstance->getStorage()->exists(FileNameHelper::get(
            $this->hash, $this->ext, $thumbnail, false,
            $scenarioInstance->shouldSaveOriginalFilename() ? $this->name : null
        ), $this->hash);
    }

    public function addThumbnail(string $thumb): self
    {
        $thumbs = $this->thumbnails ? json_decode($this->thumbnails, true) : [];
        if (!in_array($thumb, $thumbs)) {
            $thumbs[] = $thumb;
        }
        $this->thumbnails = json_encode($thumbs);
        return $this;
    }

    public function removeThumbnail(string $thumb): self
    {
        $thumbs = $this->thumbnails ? json_decode($this->thumbnails, true) : [];
        $thumbs = array_filter($thumbs, fn($a) => $a != $thumb);
        $this->thumbnails = $thumbs ? json_encode(array_values($thumbs)) : null;
        return $this;
    }

    public function isThumbnailExists(string $thumbKey): bool
    {
        $thumbs = $this->thumbnails ? json_decode($this->thumbnails, true) : [];
        return in_array($thumbKey, $thumbs);
    }

    public function isThumbnailExistsByName(string $thumbnailName): bool
    {
        $scenario = $this->scenarioInstance();

        try {
            $thumbnail = $scenario->getThumbnailByAlias($thumbnailName);
        } catch (InvalidThumbnailException $invalidThumbnailException) {
            return false;
        }

        return $this->isThumbnailExists($thumbnail->getDatabaseValue(false, false));
    }


    public function saveContentToTmpFile(): string
    {
        $scenarioInstance = $this->scenarioInstance();

        $filename = FileNameHelper::get($this->hash, $this->ext, null, false,
            $scenarioInstance->shouldSaveOriginalFilename() ? $this->name : null
        );

        $this->tmpFile = new TempFile();
        if (!$scenarioInstance->getStorage()->download($filename, $this->hash, $this->tmpFile->getPath())) {
            throw new \Exception('Can not download file - ' . $filename);
        }

        return $this->tmpFile->getPath();
    }
}
