<?php

namespace Ozerich\FileStorage\Storage;

use Illuminate\Support\Facades\Request;
use Ozerich\FileStorage\Structures\Thumbnail;

class FileStorage extends BaseStorage
{
    /** @var string */
    public $uploadDirPath;

    /** @var string */
    public $uploadDirUrl;

    /**
     * @param $file_hash
     * @return string
     */
    protected function getInnerDirectory($file_hash)
    {
        return implode(DIRECTORY_SEPARATOR, [
            mb_strtolower(mb_substr($file_hash, 0, 2)),
            mb_strtolower(mb_substr($file_hash, 2, 2))
        ]);
    }

    /**
     * @param $file_hash
     * @param $file_ext
     * @param Thumbnail|null $thumbnail
     * @param boolean $is_2x
     * @return string
     */
    protected function getFileName($file_hash, $file_ext, Thumbnail $thumbnail = null, $is_2x = false)
    {
        $result = $file_hash . ($thumbnail ? '_' . $thumbnail->getFilenamePrefix() . ($is_2x ? '@2x' : '') : '') . '.' . $file_ext;

        return $result;
    }

    /**
     * @param $file_hash
     * @param $file_ext
     * @param Thumbnail|null $thumbnail
     * @param boolean $is_2x
     * @return string
     */
    public function getAbsoluteFilePath($file_hash, $file_ext, Thumbnail $thumbnail = null, $is_2x = false, $originalFileName = null, $returnNullIfNotExists = false)
    {
        $result = $this->uploadDirPath . $this->getFilePath($file_hash, $file_ext, $thumbnail, null, $is_2x, $originalFileName);

        if ($returnNullIfNotExists && !is_file($result)) {
            return null;
        }

        return $result;
    }

    /**
     * @param $file_hash
     * @param $file_ext
     * @param Thumbnail|null $thumbnail
     * @return string
     */
    public function getFileContent($file_hash, $file_ext, Thumbnail $thumbnail = null, $originalFileName = null)
    {
        $file_path = $this->getAbsoluteFilePath($file_hash, $file_ext, $thumbnail, $originalFileName);

        if (!is_file($file_path)) {
            return null;
        }

        $f = fopen($file_path, 'r');
        $data = fread($f, filesize($file_path));
        fclose($f);

        return $data;
    }

    /**
     * @param $file_hash
     * @param $file_ext
     * @param Thumbnail|null $thumbnail
     * @param boolean $is_2x
     * @return bool
     */
    public function isFileExists($file_hash, $file_ext, Thumbnail $thumbnail = null, $is_2x = false, $originalFileName = null)
    {
        return is_file($this->getAbsoluteFilePath($file_hash, $file_ext, $thumbnail, $is_2x, $originalFileName));
    }

    /**
     * @param $src
     * @param $file_hash
     * @param $file_ext
     * @param Thumbnail|null $thumbnail
     * @param bool $is_2x
     * @return bool
     */
    public function upload($src, $file_hash, $file_ext, Thumbnail $thumbnail = null, $is_2x = false, $originalFileName = false)
    {
        $directory = $this->uploadDirPath . DIRECTORY_SEPARATOR . $this->getInnerDirectory($file_hash);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $dest = $this->getAbsoluteFilePath($file_hash, $file_ext, $thumbnail, $is_2x, $originalFileName);

        if (is_uploaded_file($src)) {
            return @move_uploaded_file($src, $dest);
        } else {
            return @rename($src, $dest);
        }
    }

    /**
     * @param $file_hash
     * @param $file_ext
     * @param $dest
     * @param Thumbnail|null $thumbnail
     * @return bool
     */
    public function download($file_hash, $file_ext, $dest, Thumbnail $thumbnail = null, $originalFileName = null)
    {
        return copy($this->getAbsoluteFilePath($file_hash, $file_ext, $thumbnail, false, $originalFileName), $dest);
    }

    /**
     * @param $file_hash
     * @param $file_ext
     * @param Thumbnail|null $thumbnail
     */
    public function delete($file_hash, $file_ext, Thumbnail $thumbnail = null, $is_2x = false, $originalFileName = null)
    {
        $this->deleteAllThumbnails($file_hash);

        @unlink($this->getAbsoluteFilePath($file_hash, $file_ext, $thumbnail, $is_2x, $originalFileName));
    }

    public function getThumbnailPathes($file_hash, $originalFileName = null)
    {
        $result = [];

        $path = $this->uploadDirPath . DIRECTORY_SEPARATOR . $this->getInnerDirectory($file_hash);

        if (!is_dir($path)) {
            return $result;
        }

        if ($handle = opendir($path)) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry == '.' || $entry == '..') {
                    continue;
                }

                if (mb_substr($entry, 0, mb_strlen($file_hash)) != $file_hash) {
                    continue;
                }

                $p = mb_strrpos($entry, '.');
                if ($p === false) {
                    continue;
                }

                $filename = mb_substr($entry, 0, $p);
                if ($originalFileName) {
                    if ($filename != $originalFileName) {
                        $result[] = $path . DIRECTORY_SEPARATOR . $entry;
                    }
                } else {
                    if ($filename != $file_hash) {
                        $result[] = $path . DIRECTORY_SEPARATOR . $entry;
                    }
                }
            }
        }

        return $result;
    }

    public function deleteAllThumbnails($file_hash, $originalFileName = null)
    {
        foreach ($this->getThumbnailPathes($file_hash, $originalFileName) as $filePath) {
            @unlink($filePath);
        }
    }

    /**
     * @param $file_hash
     * @param $file_ext
     * @param Thumbnail|null $thumbnail
     * @param boolean $is_2x
     * @return string
     */
    public function getFileUrl($file_hash, $file_ext, Thumbnail $thumbnail = null, $is_2x = false, $originalFileName = null)
    {
        if ($this->isFileExists($file_hash, $file_ext, $thumbnail, $is_2x, $originalFileName) == false) {
            return null;
        }

        return config('app.url') . $this->uploadDirUrl . $this->getFilePath($file_hash, $file_ext, $thumbnail, '/', $is_2x, $originalFileName);
    }

    /**
     * @param $file_hash
     * @param $file_ext
     * @param Thumbnail|null $thumbnail
     * @param $sep
     * @param bool $is_2x
     * @return string
     */
    public function getFilePath($file_hash, $file_ext, Thumbnail $thumbnail = null, $sep = null, $is_2x = false, $originalFileName = false)
    {
        if ($sep == null) {
            $sep = DIRECTORY_SEPARATOR;
        }

        if ($originalFileName) {
            $p = strrpos($originalFileName, '.');
            if ($p !== false) {
                $filename = substr($originalFileName, 0, $p);
            } else {
                $filename = $originalFileName;
            }
        } else {
            $filename = $file_hash;
        }

        return $sep . $this->getInnerDirectory($file_hash) . $sep . $this->getFileName($filename, $file_ext, $thumbnail, $is_2x);
    }
}
