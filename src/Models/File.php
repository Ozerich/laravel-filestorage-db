<?php

namespace Ozerich\FileStorage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ozerich\FileStorage\Exceptions\InvalidFileForScenarioException;
use Ozerich\FileStorage\Exceptions\InvalidScenarioException;
use Ozerich\FileStorage\Exceptions\InvalidThumbnailException;
use Ozerich\FileStorage\Jobs\PrepareThumbnailsJob;
use Ozerich\FileStorage\Storage;

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
 */
class File extends Model
{
    use SoftDeletes;

    protected $table = 'files';

    protected static function boot()
    {
        parent::boot();

        static::deleted(function (self $file) {
            $file->deleteSelfFiles();
        });
    }

    private function deleteSelfFiles()
    {
        $scenario = $this->scenarioInstance(false);
        if ($scenario) {
            $scenario->getStorage()->deleteAllThumbnails($this->hash, $scenario->shouldSaveOriginalFilename());
            $scenario->getStorage()->removeByPath($this->getPath());
        }
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

    /**
     * @param string|null $thumbnail_alias
     * @return string|null
     */
    public function getAbsolutePath($thumbnail_alias = null)
    {
        try {
            $scenario = $this->scenarioInstance();
        } catch (InvalidScenarioException $exception) {
            return null;
        }

        $thumbnail = null;
        if (!empty($thumbnail_alias)) {
            $thumbnail = $scenario->getThumbnailByAlias($thumbnail_alias);
        }

        return $scenario->getStorage()->getFileUrl(
            $this->hash, $this->ext, $thumbnail, false,
            $scenario->shouldSaveOriginalFilename() ? $this->name : null
        );
    }

    public function getPath()
    {
        try {
            $scenario = $this->scenarioInstance();
        } catch (InvalidScenarioException $exception) {
            return null;
        }

        $result = $scenario->getStorage()->getAbsoluteFilePath($this->hash, $this->ext, null, false,
            $scenario->shouldSaveOriginalFilename() ? $this->name : null, true
        );

        return $result ? realpath($result) : null;
    }

    public function setScenario($scenario, $regenerateThumbnails = false, $throwExceptionIfInvalid = false)
    {
        if ($this->scenario == $scenario) {
            return $this;
        }

        $oldFilePath = $this->getPath();

        $scenarioInstance = Storage::getScenario($scenario, true);

        $validator = $scenarioInstance->getValidator();
        if ($validator) {
            $validate = $validator->validate($oldFilePath, $this->name);
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

        $scenarioInstance->getStorage()->upload(
            $oldFilePath, $this->hash, $this->ext, null, false,
            $scenarioInstance->shouldSaveOriginalFilename() ? $this->name : null
        );

        if ($regenerateThumbnails && $scenarioInstance && $scenarioInstance->hasThumnbails()) {
            dispatch(new PrepareThumbnailsJob($this));
        }

        return $this;
    }

    public function getUrl($thumbnail_alias = null, $returnOriginalByDefault = true)
    {
        if (empty($thumbnail_alias)) {
            return $this->getAbsolutePath();
        }

        if (!$this->isThumbnailExists($thumbnail_alias)) {
            return $this->getAbsolutePath();
        }

        if ($returnOriginalByDefault && !$this->isFileExists($thumbnail_alias)) {
            return $this->getAbsolutePath();
        }

        return $this->getAbsolutePath($thumbnail_alias);
    }

    private function dashesToCamelCase($string, $capitalizeFirstCharacter = false)
    {
        $str = str_replace('-', '', ucwords($string, '-'));

        if (!$capitalizeFirstCharacter) {
            $str = lcfirst($str);
        }

        return $str;
    }

    public function getThumbnailsJson($thumbnails)
    {
        $scenarioInstance = $this->scenarioInstance();
        if ($scenarioInstance->getStorage()->isFileExists($this->hash, $this->ext, null, false, $scenarioInstance->shouldSaveOriginalFilename() ? $this->name : null) == false) {
            return null;
        }

        if (!is_array($thumbnails)) {
            $thumbnails = [$thumbnails];
        }

        $result = [];
        foreach ($thumbnails as $thumbnail) {
            try {
                $value = $this->getThumbnailJson($thumbnail);
            } catch (InvalidThumbnailException $exception) {
                $value = null;
            }
            $result[$this->dashesToCamelCase($thumbnail)] = $value;
        }

        return $result;
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

    public function getThumbnailJson($thumbnailName, $scenario = null)
    {
        if ($this->mime == 'image/svg' || $this->mime == 'image/svg+xml') {
            return [
                'url' => $this->getUrl()
            ];
        }

        if ($scenario && $this->scenario != $scenario) {
            $this->setScenario($scenario);
        }

        $scenario = $this->scenarioInstance();

        if ($this->isFileExists($thumbnailName) == false) {
            return [
                'url' => $this->getUrl()
            ];
        }

        try {
            $thumbnail = $scenario->getThumbnailByAlias($thumbnailName);
        } catch (InvalidThumbnailException $invalidThumbnailException) {
            return null;
        }

        $originalFilename = $scenario->shouldSaveOriginalFilename() ? $this->name : null;

        $url = $scenario->getStorage()->getFileUrl($this->hash, $this->ext, $thumbnail, false, $originalFilename);
        $url2x = $thumbnail->is2xSupport() && $scenario->getStorage()->isFileExists($this->hash, $this->ext, $thumbnail, true, $originalFilename) ? $scenario->getStorage()->getFileUrl($this->hash, $this->ext, $thumbnail, true, $originalFilename) : null;
        $url_webp = $thumbnail->isWebpSupport() ? $scenario->getStorage()->getFileUrl($this->hash, 'webp', $thumbnail, false, $originalFilename) : null;
        $url_webp2x = $thumbnail->isWebpSupport() && $thumbnail->is2xSupport() && $scenario->getStorage()->isFileExists($this->hash, 'webp', $thumbnail, true, $originalFilename) ? $scenario->getStorage()->getFileUrl($this->hash, 'webp', $thumbnail, true, $originalFilename) : null;

        $item = [
            'url' => $url,
        ];

        if ($thumbnail->is2xSupport()) {
            $item['url_2x'] = $url2x;
        }

        if ($thumbnail->isWebpSupport()) {
            $item['url_webp'] = $url_webp;
            if ($thumbnail->isWebpSupport() && $thumbnail->is2xSupport()) {
                $item['url_webp_2x'] = $url_webp2x;
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

    public function getFullJson($scenario = null, $withOriginalUrl = false, $regenerateThumbnailsIfNeeded = true, $exceptThumbnails = [])
    {
        if ($scenario && $this->scenario != $scenario) {
            $this->setScenario($scenario);
        }

        try {
            $scenarioInstance = $this->scenarioInstance();
        } catch (InvalidScenarioException $exception) {
            return null;
        }

        if (!$scenarioInstance->getStorage()->isFileExists(
            $this->hash, $this->ext, null, false,
            $scenarioInstance->shouldSaveOriginalFilename() ? $this->name : null
        )) return null;


        $thumbs = [];
        if ($scenarioInstance->hasThumnbails()) {
            if ($regenerateThumbnailsIfNeeded) {
                Storage::staticPrepareThumbnails($this);
            }

            $thumbnails = $scenarioInstance->getThumbnails();
            $thumbnailsFiltered = [];
            if (!empty($exceptThumbnails)) {
                foreach ($thumbnails as $thumbnailAlias => $thumbnailInstance) {
                    if (in_array($thumbnailAlias, $exceptThumbnails) == false) {
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

        if (!$withOriginalUrl) {
            if (empty($thumbs)) {
                return [
                    'url' => $this->getUrl()
                ];
            } else {
                return $thumbs;
            }
        }

        return [
            'url' => $this->getUrl(),
            'thumbnails' => $thumbs
        ];
    }

    /**
     * @return bool
     */
    public function isFileExists($thumbnailAlias = null)
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

        return $scenarioInstance->getStorage()->isFileExists(
            $this->hash, $this->ext, $thumbnail, false, $scenarioInstance->shouldSaveOriginalFilename()
        );
    }

    /**
     * @param string $thumbnail
     * @return bool
     */
    public function isThumbnailExists($thumbnail)
    {
        try {
            $scenarioInstance = $this->scenarioInstance();
        } catch (InvalidScenarioException $exception) {
            return false;
        }

        try {
            $scenarioInstance->getThumbnailByAlias($thumbnail);
            return true;
        } catch (InvalidThumbnailException $exception) {
            return false;
        }
    }
}
