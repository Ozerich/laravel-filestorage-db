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
            'extensions' => ['jpg', 'jpeg', 'png', 'zip', 'docx', 'pdf', 'doc', 'rar', 'xls', 'xlsx', 'pptx', 'ppt', 'gif', 'mp4', 'svg', 'fig', 'psd', 'txt', 'csv', '7z']
        ];
    }

    public static function imageValidator($maxSizeMB = 50, $includeGif = false, $includeSvg = true)
    {
        $extensions = ['jpg', 'jpeg', 'png', 'webp'];

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

    public static function videoValidator()
    {
        return [
            'maxSize' => 1000 * 1024 * 1024,
            'checkExtensionByMimeType' => true,
            'extensions' => ['mp4']
        ];
    }

    public static function fileStorage($folderName, $innerFoldersCount = 2)
    {
        return [
            'type' => 'file',
            'uploadDirPath' => __DIR__ . '/../../../../../storage/app/public/uploads/' . $folderName,
            'uploadDirUrl' => '/uploads/' . $folderName,
            'innerFoldersCount' => $innerFoldersCount
        ];
    }

    public static function temporaryStorage()
    {
        return self::fileStorage('tmp');
    }

    private static function validateThumb($width, $height, $mode)
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
        self::validateThumb($width, $height, $mode);

        return [
            'width' => $width,
            'height' => $height,
            'webp' => false,
            '2x' => false,
            'crop' => $mode == self::MODE_CROP,
            'exact' => $mode == self::MODE_EXACT,
            'force' => $forceResize,
            'force2x' => false,
            'quality' => $quality
        ];
    }

    public static function thumbWithWebp($width = null, $height = null, $mode = self::MODE_AUTO, $forceResize = false, $quality = null)
    {
        self::validateThumb($width, $height, $mode);

        return [
            'width' => $width,
            'height' => $height,
            'webp' => true,
            '2x' => false,
            'crop' => $mode == self::MODE_CROP,
            'exact' => $mode == self::MODE_EXACT,
            'force' => $forceResize,
            'force2x' => false,
            'quality' => $quality
        ];
    }

    public static function thumbWith2x($width = null, $height = null, $mode = self::MODE_AUTO, $forceResize = true, $force2xResize = false, $quality = null)
    {
        self::validateThumb($width, $height, $mode);

        return [
            'width' => $width,
            'height' => $height,
            'webp' => false,
            '2x' => true,
            'crop' => $mode == self::MODE_CROP,
            'exact' => $mode == self::MODE_EXACT,
            'force' => $forceResize,
            'force2x' => $force2xResize,
            'quality' => $quality
        ];
    }

    public static function thumbWithWebpAnd2x($width = null, $height = null, $mode = self::MODE_AUTO, $forceResize = true, $force2xResize = false, $quality = null)
    {
        self::validateThumb($width, $height, $mode);

        return [
            'width' => $width,
            'height' => $height,
            'crop' => $mode == self::MODE_CROP,
            'exact' => $mode == self::MODE_EXACT,
            '2x' => true,
            'webp' => true,
            'force' => $forceResize,
            'force2x' => $force2xResize,
            'quality' => $quality
        ];
    }

    public static function thumbOpenGraph()
    {
        return self::thumb(1200, 630, false, true);
    }

    public static function openGraphThumb()
    {
        return self::thumbOpenGraph();
    }

    public static function openGraphValidator()
    {
        return self::imageValidator(20, false, false);
    }

    public static function backgroundThumbnails($use2x = false)
    {
        if ($use2x == false) {
            return [
                'desktop' => self::thumbWithWebp(1920, null, self::MODE_AUTO, false),
                'laptop' => self::thumbWithWebp(1500, null, self::MODE_AUTO, false),
                'tablet' => self::thumbWithWebp(1024, null, self::MODE_AUTO, false),
                'mobile' => self::thumbWithWebp(425, null, self::MODE_AUTO, false),
            ];
        } else {
            return [
                'desktop' => self::thumbWithWebpAnd2x(1920, null, self::MODE_AUTO, false),
                'laptop' => self::thumbWithWebpAnd2x(1500, null, self::MODE_AUTO, false),
                'tablet' => self::thumbWithWebpAnd2x(1024, null, self::MODE_AUTO, false),
                'mobile' => self::thumbWithWebpAnd2x(425, null, self::MODE_AUTO, false),
            ];
        }
    }
}
