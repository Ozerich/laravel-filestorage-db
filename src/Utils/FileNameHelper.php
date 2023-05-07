<?php

namespace Ozerich\FileStorage\Utils;

use Ozerich\FileStorage\Structures\Thumbnail;

class FileNameHelper
{
    public static function get(string $fileHash, string $fileExt, Thumbnail $thumbnail = null, $is2x = false, ?string $originalFileName = null): string
    {
        $baseFileName = $originalFileName ?? $fileHash;

        return $baseFileName . ($thumbnail ? '_' . $thumbnail->getFilenamePrefix() . ($is2x ? '@2x' : '') : '') . '.' . $fileExt;
    }
}
