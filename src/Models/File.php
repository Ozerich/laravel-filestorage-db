<?php

namespace Ozerich\FileStorage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ozerich\FileStorage\Exceptions\InvalidScenarioException;
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
     * @param string|null $thumbnail_alias
     * @return string
     */
    public function getAbsolutePath($thumbnail_alias = null)
    {
        $scenario = Storage::getScenario($this->scenario);

        $thumbnail = null;
        if (!empty($thumbnail_alias)) {
            $thumbnail = $scenario->getThumbnailByAlias($thumbnail_alias);
        }

        return $scenario->getStorage()->getFileUrl($this->hash, $this->ext, $thumbnail);
    }

    public function getPath()
    {
        $scenario = Storage::getScenario($this->scenario);

        if (!$scenario) {
            return null;
        }

        return $scenario->getStorage()->getAbsoluteFilePath($this->hash, $this->ext);
    }

    public function setScenario($scenario, $regenerateThumbnails = false)
    {
        $oldFilePath = $this->getPath();

        if ($this->scenario == $scenario) {
            return $this;
        }

        $this->scenario = $scenario;
        $this->save();

        $scenarioInstance = Storage::getScenario($this->scenario);
        if (!$scenarioInstance) {
            throw new InvalidScenarioException('Scenario "' . $this->scenario . '" not found');
        }

        $scenarioInstance->getStorage()->upload($oldFilePath, $this->hash, $this->ext);

        if ($regenerateThumbnails && $scenarioInstance && $scenarioInstance->hasThumnbails()) {
            dispatch(new PrepareThumbnailsJob($this));
        }

        return $this;
    }

    public function getUrl($thumbnail_alias = null)
    {
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
        $scenarioInstance = Storage::getScenario($this->scenario);
        if ($scenarioInstance->getStorage()->isFileExists($this->hash, $this->ext) == false) {
            return null;
        }

        if (!is_array($thumbnails)) {
            $thumbnails = [$thumbnails];
        }

        $result = [];
        foreach ($thumbnails as $thumbnail) {
            $result[$this->dashesToCamelCase($thumbnail)] = $this->getThumbnailJson($thumbnail);
        }

        return $result;
    }
    
    public function getThumbnailJson($thumbnailId)
    {
        $scenario = Storage::getScenario($this->scenario);

        $thumbnail = $scenario->getThumbnailByAlias($thumbnailId);
        if (!$thumbnail) {
            return null;
        }

        $url = $scenario->getStorage()->getFileUrl($this->hash, $this->ext, $thumbnail);
        $url2x = $thumbnail->is2xSupport() ? $scenario->getStorage()->getFileUrl($this->hash, $this->ext, $thumbnail, true) : null;
        $url_webp = $thumbnail->isWebpSupport() ? $scenario->getStorage()->getFileUrl($this->hash, 'webp', $thumbnail, false) : null;
        $url_webp2x = $thumbnail->isWebpSupport() && $thumbnail->is2xSupport() ? $scenario->getStorage()->getFileUrl($this->hash, 'webp', $thumbnail, true) : null;

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

        $scenarioInstance = Storage::getScenario($this->scenario);
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
