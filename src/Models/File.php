<?php

namespace Ozerich\FileStorage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ozerich\FileStorage\Exceptions\InvalidScenarioException;
use Ozerich\FileStorage\Exceptions\InvalidThumbnailException;
use Ozerich\FileStorage\Jobs\PrepareThumbnailsJob;
use Ozerich\FileStorage\Storage;

class File extends Model
{
    use SoftDeletes;

    protected $table = 'files';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'price',
        'price_old',
        'is_new',
        'is_popular',
        'count',
        'count_text',
        'description',
        'url_alias',
    ];

    /**
     * @return \Ozerich\FileStorage\Structures\Scenario
     * @throws InvalidScenarioException
     */
    private function scenarioInstance()
    {
        $scenario = Storage::getScenario($this->scenario);
        if (!$scenario) {
            throw new InvalidScenarioException('Scenario "' . $this->scenario . '" not found');
        }
        return $scenario;
    }

    /**
     * @param string|null $thumbnail_alias
     * @return string
     */
    public function getAbsolutePath($thumbnail_alias = null)
    {
        $scenario = $this->scenarioInstance();

        $thumbnail = null;
        if (!empty($thumbnail_alias)) {
            $thumbnail = $scenario->getThumbnailByAlias($thumbnail_alias);
        }

        return $scenario->getStorage()->getFileUrl($this->hash, $this->ext, $thumbnail);
    }

    public function getPath()
    {
        try {
            $scenario = $this->scenarioInstance();
        } catch (InvalidScenarioException $exception) {
            return null;
        }

        return $scenario->getStorage()->getAbsoluteFilePath($this->hash, $this->ext);
    }

    public function setScenario($scenario, $regenerateThumbnails = false)
    {
        if ($this->scenario == $scenario) {
            return $this;
        }

        $oldFilePath = $this->getPath();

        $scenarioInstance = Storage::getScenario($scenario, true);

        $this->scenario = $scenarioInstance->getId();
        $this->save();

        $scenarioInstance->getStorage()->upload($oldFilePath, $this->hash, $this->ext);

        if ($regenerateThumbnails && $scenarioInstance && $scenarioInstance->hasThumnbails()) {
            dispatch(new PrepareThumbnailsJob($this));
        }

        return $this;
    }

    public function getUrl($thumbnail_alias = null)
    {
        if ($this->mime == 'image/svg' || $this->mime == 'image/svg+xml') {
            return $this->getUrl();
        }

        try {
            return $this->getAbsolutePath($thumbnail_alias);
        } catch (InvalidThumbnailException $exception) {
            return $this->getAbsolutePath();
        }
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
        if ($scenarioInstance->getStorage()->isFileExists($this->hash, $this->ext) == false) {
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

    public function getDefaultThumbnailUrl($scenario)
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

    public function getThumbnailJson($thumbnail, $scenario = null)
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
        $thumbnail = $scenario->getThumbnailByAlias($thumbnail);

        $url = $scenario->getStorage()->getFileUrl($this->hash, $this->ext, $thumbnail);
        $url2x = $thumbnail->is2xSupport() && $scenario->getStorage()->isFileExists($this->hash, $this->ext, $thumbnail, true) ? $scenario->getStorage()->getFileUrl($this->hash, $this->ext, $thumbnail, true) : null;
        $url_webp = $thumbnail->isWebpSupport() ? $scenario->getStorage()->getFileUrl($this->hash, 'webp', $thumbnail, false) : null;
        $url_webp2x = $thumbnail->isWebpSupport() && $thumbnail->is2xSupport() && $scenario->getStorage()->isFileExists($this->hash, 'webp', $thumbnail, true) ? $scenario->getStorage()->getFileUrl($this->hash, 'webp', $thumbnail, true) : null;

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

    public function getShortJson()
    {
        return [
            'id' => $this->id,
            'mime' => $this->mime,
            'name' => $this->name,
            'size' => $this->size,
            'url' => $this->getUrl()
        ];
    }

    public function getFullJson($scenario = null, $withOriginalUrl = false, $regenerateThumbnailsIfNeeded = true)
    {
        if ($scenario && $this->scenario != $scenario) {
            $this->setScenario($scenario);
        }

        $scenarioInstance = $this->scenarioInstance();
        if ($scenarioInstance->getStorage()->isFileExists($this->hash, $this->ext) == false) {
            return null;
        }

        $thumbs = [];
        if ($scenarioInstance->hasThumnbails()) {
            if ($regenerateThumbnailsIfNeeded) {
                Storage::staticPrepareThumbnails($this);
            }

            foreach ($scenarioInstance->getThumbnails() as $alias => $thumbnail) {
                if ($scenarioInstance->isSingleThumbnail() && $alias == 'default') {
                    $thumbs = $this->getThumbnailJson($alias);
                } else {
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
}
