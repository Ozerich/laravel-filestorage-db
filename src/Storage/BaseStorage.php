<?php

namespace Ozerich\FileStorage\Storage;

use Ozerich\FileStorage\Structures\Thumbnail;

abstract class BaseStorage
{
    public function __construct($config)
    {
        foreach ($config as $param => $value) {
            if (!in_array($param, ['class', 'type'])) {
                $this->$param = $value;
            }
        }
    }

    abstract function isFileExists($file_hash, $file_ext, Thumbnail $thumbnail = null, $is_2x = false, $originalFileName = null);

    abstract function upload($src, $file_hash, $file_ext, Thumbnail $thumbnail = null, $is_2x = false, $originalFileName = null);

    abstract function download($file_hash, $file_ext, $dest, Thumbnail $thumbnail = null, $originalFileName = null);

    abstract function delete($file_hash, $file_ext, Thumbnail $thumbnail = null, $is_2x = false, $originalFileName = null);

    abstract function deleteAllThumbnails($file_hash, $originalFileName = null);

    abstract function getFileUrl($file_hash, $file_ext, Thumbnail $thumbnail = null, $is_2x = false, $originalFileName = null);

    abstract function getFilePath($file_hash, $file_ext, Thumbnail $thumbnail = null, $originalFileName = null);

    abstract function getAbsoluteFilePath($file_hash, $file_ext, Thumbnail $thumbnail = null, $originalFileName = null);

    abstract function getFileContent($file_hash, $file_ext, Thumbnail $thumbnail = null, $originalFileName = null);
}
