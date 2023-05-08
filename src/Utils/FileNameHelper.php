<?php

namespace Ozerich\FileStorage\Utils;

use Ozerich\FileStorage\Structures\Thumbnail;

class FileNameHelper
{
    public static function get(string $fileHash, string $fileExt, Thumbnail $thumbnail = null, $is2x = false, ?string $originalFileName = null): string
    {
        if ($originalFileName) {
            $p = strrpos($originalFileName, '.');
            $baseFileName = $p !== false ? substr($originalFileName, 0, $p) : $originalFileName;
        } else {
            $baseFileName = $fileHash;
        }

        return $baseFileName . ($thumbnail ? '_' . $thumbnail->getFilenamePrefix() . ($is2x ? '@2x' : '') : '') . '.' . $fileExt;
    }

    public static function getForSize(string $fileHash, string $fileExt, ?int $width, ?int $height, bool $is2x = false, ?string $originalFileName = null): string
    {
        if ($originalFileName) {
            $p = strrpos($originalFileName, '.');
            $baseFileName = $p !== false ? substr($originalFileName, 0, $p) : $originalFileName;
        } else {
            $baseFileName = $fileHash;
        }

        return $baseFileName . '_' . ($width ?? 'AUTO') . '_' . ($height ?? 'AUTO') . ($is2x ? '@2x' : '') . '.' . $fileExt;
    }
}
