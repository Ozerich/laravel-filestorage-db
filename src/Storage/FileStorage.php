<?php

namespace Ozerich\FileStorage\Storage;

use Illuminate\Support\Facades\Request;
use Ozerich\FileStorage\Structures\Thumbnail;
use Ozerich\FileStorage\Utils\FileNameHelper;

class FileStorage extends BaseStorage
{
    /** @var string */
    public string $uploadDirPath;
    /** @var string */
    public string $uploadDirUrl;
    /** @var int */
    public int $innerFoldersCount = 2;

    public function __construct($config)
    {
        parent::__construct($config);

        $this->innerFoldersCount = min(4, $this->innerFoldersCount);
    }

    public function isFileExists($filename): bool
    {
        $fullPath = realpath($this->uploadDirPath . DIRECTORY_SEPARATOR . $this->getInnerDirectory($filename));
        $filePath = $fullPath . DIRECTORY_SEPARATOR . $filename;

        return is_file($filePath);
    }

    public function upload(string $src, string $dest, bool $deleteSrc = false): bool
    {
        $directory = $this->uploadDirPath . DIRECTORY_SEPARATOR . $this->getInnerDirectory($dest);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $dest = $directory . DIRECTORY_SEPARATOR . $dest;

        if (is_uploaded_file($src)) {
            return @move_uploaded_file($src, $dest);
        } else {
            if ($deleteSrc) {
                return @rename($src, $dest);
            } else {
                return @copy($src, $dest);
            }
        }
    }

    public function download(string $filename, string $dest): bool
    {
        $fullPath = realpath($this->uploadDirPath . DIRECTORY_SEPARATOR . $this->getInnerDirectory($filename));
        $filePath = $fullPath . DIRECTORY_SEPARATOR . $filename;
        if (!is_file($filePath)) return false;

        return copy($filePath, $dest);
    }

    public function delete(string $filename): bool
    {
        $fullPath = realpath($this->uploadDirPath . DIRECTORY_SEPARATOR . $this->getInnerDirectory($filename));
        $filePath = $fullPath . DIRECTORY_SEPARATOR . $filename;

        return @unlink($filePath);
    }

    public function getFileUrl(string $filename): string
    {
        return config('app.url') . $this->uploadDirUrl . '/' . $this->getInnerDirectory($filename) . '/' . $filename;
    }

    public function getBody(string $filename): ?string
    {
        $fullPath = realpath($this->uploadDirPath . DIRECTORY_SEPARATOR . $this->getInnerDirectory($filename));
        $filePath = $fullPath . DIRECTORY_SEPARATOR . $filename;

        if (!is_file($filePath)) return false;
        return file_get_contents($filePath);
    }

    protected function getInnerDirectory(string $fileName): string
    {
        $result = [];

        for ($i = 0; $i < $this->innerFoldersCount; $i++) {
            $result[] = mb_strtolower(mb_substr($fileName, $i * 2, 2));
        }

        return implode(DIRECTORY_SEPARATOR, $result);
    }

    private function normalizePath($path, $separator = '\\/')
    {
        // Remove any kind of funky unicode whitespace
        $normalized = preg_replace('#\p{C}+|^\./#u', '', $path);

        // Path remove self referring paths ("/./").
        $normalized = preg_replace('#/\.(?=/)|^\./|\./$#', '', $normalized);

        // Regex for resolving relative paths
        $regex = '#\/*[^/\.]+/\.\.#Uu';

        while (preg_match($regex, $normalized)) {
            $normalized = preg_replace($regex, '', $normalized);
        }

        if (preg_match('#/\.{2}|\.{2}/#', $normalized)) {
            throw new LogicException('Path is outside of the defined root, path: [' . $path . '], resolved: [' . $normalized . ']');
        }

        return trim($normalized, $separator);
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


    public function removeByPath(?string $path): bool
    {
        if (!$path) return false;
        return @unlink($path);
    }

    public function getThumbnailPathes($file_hash, $originalFileName = null)
    {
        $path = $this->uploadDirPath . DIRECTORY_SEPARATOR . $this->getInnerDirectory($file_hash);
        if (!is_dir($path)) {
            return [];
        }

        $result = [];
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
                if (($originalFileName && $filename != $originalFileName) || (!$originalFileName && $filename != $file_hash)) {
                    $result[] = realpath($path . DIRECTORY_SEPARATOR . $entry);
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

        $innerDirectory = $this->getInnerDirectory($file_hash);
        if (!empty($innerDirectory)) {
            $innerDirectory = $sep . $innerDirectory;
        }

        $filename = FileNameHelper::get($filename, $file_ext, $thumbnail, $is_2, $originalFileName);
        return $innerDirectory . $sep . $filename;
    }
}
