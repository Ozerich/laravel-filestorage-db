<?php

namespace Ozerich\FileStorage\Utils;

use Ozerich\FileStorage\Exceptions\InvalidConfigException;

class ConfigHelper
{
    public static function defaultValidator($maxSizeMB = 50)
    {
        return [
            'maxSize' => $maxSizeMB * 1024 * 1024,
            'checkExtensionByMimeType' => true,
            'extensions' => ['jpg', 'jpeg', 'png', 'zip', 'docx', 'pdf', 'doc', 'rar', 'xls', 'xlsx', 'pptx', 'ppt', 'gif', 'mp4']
        ];
    }

    public static function imageValidator($maxSizeMB = 50, $includeGif = false)
    {
        $extensions = ['jpg', 'jpeg', 'png'];
        if ($includeGif) {
            $extensions[] = 'gif';
        }

        return [
            'maxSize' => $maxSizeMB * 1024 * 1024,
            'checkExtensionByMimeType' => true,
            'extensions' => $extensions
        ];
    }

    public static function fileStorage($folderName)
    {
        return [
            'type' => 'file',
            'saveOriginalFilename' => false,
            'uploadDirPath' => __DIR__ . '/../../../../../storage/app/public/uploads/' . $folderName,
            'uploadDirUrl' => '/uploads/' . $folderName,
        ];
    }

    public static function temporaryStorage()
    {
        return self::fileStorage('tmp');
    }

    public static function thumb($width = null, $height = null, $crop = false, $exact = false, $force = false, $quality = null)
    {
        return [
            'width' => $width,
            'height' => $height,
            'webp' => false,
            '2x' => false,
            'crop' => $crop,
            'exact' => $exact,
            'force' => $force,
            'quality' => $quality
        ];
    }

    public static function thumbOpenGraph()
    {
        return self::thumb(1200, 630, false, true);
    }

    public static function thumbWithWebp($width = null, $height = null, $crop = false, $exact = false, $force = false, $quality = null)
    {
        if ($crop && $exact) {
            throw new InvalidConfigException('Invalid thumbnail: can not be CROP and EXACT at the same time');
        }

        if ($force && (!$width || !$height)) {
            throw new InvalidConfigException('Invalid thumbnail: for using FORCE mode you must specify width and height');
        }

        return [
            'width' => $width,
            'height' => $height,
            'webp' => true,
            '2x' => false,
            'crop' => $crop,
            'exact' => $exact,
            'force' => $force,
            'quality' => $quality
        ];
    }

    public static function thumbWith2x($width = null, $height = null, $crop = false, $exact = false, $force = false, $quality = null)
    {
        if ($crop && $exact) {
            throw new InvalidConfigException('Invalid thumbnail: can not be CROP and EXACT at the same time');
        }

        if ($force && (!$width || !$height)) {
            throw new InvalidConfigException('Invalid thumbnail: for using FORCE mode you must specify width and height');
        }

        return [
            'width' => $width,
            'height' => $height,
            'webp' => false,
            '2x' => true,
            'crop' => $crop,
            'exact' => $exact,
            'force' => $force,
            'quality' => $quality
        ];
    }

    public static function thumbWithWebpAnd2x($width, $height, $crop = true, $exact = false, $force = false, $quality = null)
    {
        if ($crop && $exact) {
            throw new InvalidConfigException('Invalid thumbnail: can not be CROP and EXACT at the same time');
        }

        if ($force && (!$width || !$height)) {
            throw new InvalidConfigException('Invalid thumbnail: for using FORCE mode you must specify width and height');
        }

        return [
            'width' => $width,
            'height' => $height,
            'crop' => $crop,
            'exact' => $exact,
            '2x' => true,
            'webp' => true,
            'force' => $force,
            'quality' => $quality
        ];
    }
}
