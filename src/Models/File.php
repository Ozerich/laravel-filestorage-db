<?php

namespace Ozerich\FileStorage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
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

    private function setScenario($scenario)
    {
        $this->scenario = $scenario;
        $this->save();
    }

    public function getUrl($thumbnail_alias = null)
    {
        return $this->getAbsolutePath($thumbnail_alias);
    }

    public function getUploadResponseJson()
    {
        return [
            'id' => $this->id,
            'url' => $this->getAbsolutePath()
        ];
    }

    public function getShortJson()
    {
        return [
            'id' => $this->id,
            'url' => $this->getAbsolutePath()
        ];
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
        $url_webp2x = $thumbnail->isWebpSupport() ? $scenario->getStorage()->getFileUrl($this->hash, 'webp', $thumbnail, true) : null;

        $item = [
            'url' => $url,
        ];

        if ($thumbnail->is2xSupport()) {
            $item['url_2x'] = $url2x;
        }

        if ($thumbnail->isWebpSupport()) {
            $item['url_webp'] = $url_webp;
            if ($thumbnail->isWebpSupport()) {
                $item['url_webp_2x'] = $url_webp2x;
            }
        }

        return $item;
    }

    private function dashesToCamelCase($string, $capitalizeFirstCharacter = false)
    {
        $str = str_replace('-', '', ucwords($string, '-'));

        if (!$capitalizeFirstCharacter) {
            $str = lcfirst($str);
        }

        return $str;
    }

    public function getFullJson($scenario = null)
    {
        if ($scenario && $this->scenario != $scenario) {
            $this->setScenario($scenario);
        }

        $scenarioInstance = Storage::getScenario($this->scenario);

        if ($scenarioInstance->getStorage()->isFileExists($this->hash, $this->ext) == false) {
            return null;
        }

        $result = [

        ];

        if ($scenarioInstance->hasThumnbails()) {
            Storage::staticPrepareThumbnails($this);

            $thumbs = [];

            foreach ($scenarioInstance->getThumbnails() as $alias => $thumbnail) {
                $thumbs[$this->dashesToCamelCase($alias)] = $this->getThumbnailJson($alias);
            }

            $result = $thumbs;
        }

        return $result;
    }
}
