<?php

namespace Ozerich\FileStorage\Utils;

use Ozerich\FileStorage\Exceptions\InvalidConfigException;

class ConfigHelper
{
    const MODE_AUTO = 'AUTO';
    const MODE_EXACT = 'EXACT';
    const MODE_CROP = 'CROP';

    public static function defaultValidator($maxSizeMB = 50)
    {
        return [
            'maxSize' => $maxSizeMB * 1024 * 1024,
            'checkExtensionByMimeType' => true,
            'extensions' => ['jpg', 'jpeg', 'png', 'zip', 'docx', 'pdf', 'doc', 'rar', 'xls', 'xlsx', 'pptx', 'ppt', 'gif', 'mp4', 'svg']
        ];
    }

    public static function imageValidator($maxSizeMB = 50, $includeGif = false, $includeSvg = true)
    {
        $extensions = ['jpg', 'jpeg', 'png'];
        if ($includeGif) {
            $extensions[] = 'gif';
        }
        if ($includeSvg) {
            $extensions[] = 'svg';
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

    private static function validate($width, $height, $mode)
    {
        if ($mode == self::MODE_CROP) {
            if (!$width || !$height) {
                throw new InvalidConfigException('You must specify width and height when you use CROP mode');
            }
        }

        if ($mode == self::MODE_EXACT) {
            if (!$width || !$height) {
                throw new InvalidConfigException('You must specify width and height when you use EXACT mode');
            }
        }
    }

    public static function thumb($width = null, $height = null, $mode = self::MODE_AUTO, $forceResize = true, $quality = null)
    {
        self::validate($width, $height, $mode);

        return [
            'width' => $width,
            'height' => $height,
            'webp' => false,
            '2x' => false,
            'crop' => $mode == self::MODE_CROP,
            'exact' => $mode == self::MODE_EXACT,
            'force' => $forceResize,
            'quality' => $quality
        ];
    }

    public static function thumbOpenGraph()
    {
        return self::thumb(1200, 630, false, true);
    }

    public static function thumbWithWebp($width = null, $height = null, $mode = self::MODE_AUTO, $forceResize = false, $quality = null)
    {
        self::validate($width, $height, $mode);

        return [
            'width' => $width,
            'height' => $height,
            'webp' => true,
            '2x' => false,
            'crop' => $mode == self::MODE_CROP,
            'exact' => $mode == self::MODE_EXACT,
            'force' => $forceResize,
            'quality' => $quality
        ];
    }

    public static function thumbWith2x($width = null, $height = null, $mode = self::MODE_AUTO, $forceResize = true, $quality = null)
    {
        self::validate($width, $height, $mode);

        return [
            'width' => $width,
            'height' => $height,
            'webp' => false,
            '2x' => true,
            'crop' => $mode == self::MODE_CROP,
            'exact' => $mode == self::MODE_EXACT,
            'force' => $forceResize,
            'quality' => $quality
        ];
    }

    public static function thumbWithWebpAnd2x($width = null, $height = null, $mode = self::MODE_AUTO, $forceResize = true, $quality = null)
    {
        self::validate($width, $height, $mode);

        return [
            'width' => $width,
            'height' => $height,
            'crop' => $mode == self::MODE_CROP,
            'exact' => $mode == self::MODE_EXACT,
            '2x' => true,
            'webp' => true,
            'force' => $forceResize,
            'quality' => $quality
        ];
    }
}
