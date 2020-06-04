<?php

namespace Ozerich\FileStorage\Models;

use Ozerich\FileStorage\Storage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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

        return $scenario->getStorage()->getFileUrl($this->hash, $this->ext);
    }

    public function getUrl()
    {
        return $this->getAbsolutePath();
    }

    public function getJson()
    {
        return [
            'id' => $this->id,
            'url' => $this->getAbsolutePath()
        ];
    }

    private function dashesToCamelCase($string, $capitalizeFirstCharacter = false)
    {
        $str = str_replace('-', '', ucwords($string, '-'));

        if (!$capitalizeFirstCharacter) {
            $str = lcfirst($str);
        }

        return $str;
    }

    public function getFullJson()
    {
        $scenario = Storage::getScenario($this->scenario);

        if ($scenario->getStorage()->isFileExists($this->hash, $this->ext) == false) {
            return null;
        }

        $result = [

        ];

        if ($scenario->hasThumnbails()) {
            Storage::staticPrepareThumbnails($this);

            $thumbs = [];

            foreach ($scenario->getThumbnails() as $alias => $thumbnail) {
                $url = $scenario->getStorage()->getFileUrl($this->hash, $this->ext, $thumbnail);
                $url2x = $thumbnail->is2xSupport() ? $scenario->getStorage()->getFileUrl($this->hash, $this->ext, $thumbnail, true) : null;
                $url_webp = $thumbnail->isWebpSupport() ? $scenario->getStorage()->getFileUrl($this->hash, 'webp', $thumbnail, false) : null;
                $url_webp2x = $thumbnail->isWebpSupport() ? $scenario->getStorage()->getFileUrl($this->hash, 'webp', $thumbnail, true) : null;

                $item = [
                    'thumb' => $thumbnail->getThumbId(),
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

                $thumbs[$this->dashesToCamelCase($alias)] = $item;
            }

            $result = $thumbs;
        }


        return $result;
    }
}
