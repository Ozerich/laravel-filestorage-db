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

    private function getInnerDirectory(string $fileHash): string
    {
        $result = [];

        for ($i = 0; $i < $this->innerFoldersCount; $i++) {
            $result[] = mb_strtolower(mb_substr($fileHash, $i * 2, 2));
        }

        return implode(DIRECTORY_SEPARATOR, $result);
    }

    public function getLocalPath(string $filename, string $hash): string{
        $fullPath = realpath($this->uploadDirPath . DIRECTORY_SEPARATOR . $this->getInnerDirectory($hash));
       return $fullPath . DIRECTORY_SEPARATOR . $filename;
    }

    public function exists(string $filename, string $hash): bool
    {
        return is_file($this->getLocalPath($filename, $hash));
    }

    public function upload(string $src, string $dest, string $hash, bool $deleteSrc = false): bool
    {
        $directory = $this->uploadDirPath . DIRECTORY_SEPARATOR . $this->getInnerDirectory($hash);

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

    public function download(string $filename, string $hash, string $dest): bool
    {
        $fullPath = realpath($this->uploadDirPath . DIRECTORY_SEPARATOR . $this->getInnerDirectory($hash));
        $filePath = $fullPath . DIRECTORY_SEPARATOR . $filename;
        if (!is_file($filePath)) return false;

        return copy($filePath, $dest);
    }

    public function delete(string $filename, string $hash): bool
    {
        $fullPath = realpath($this->uploadDirPath . DIRECTORY_SEPARATOR . $this->getInnerDirectory($hash));
        $filePath = $fullPath . DIRECTORY_SEPARATOR . $filename;

        return @unlink($filePath);
    }

    public function getUrl(string $filename, string $hash): string
    {
        return config('app.url') . $this->uploadDirUrl . '/' . $this->getInnerDirectory($hash) . '/' . $filename;
    }

    public function getBody(string $filename, string $hash): ?string
    {
        $fullPath = realpath($this->uploadDirPath . DIRECTORY_SEPARATOR . $this->getInnerDirectory($hash));
        $filePath = $fullPath . DIRECTORY_SEPARATOR . $filename;

        if (!is_file($filePath)) return false;
        return file_get_contents($filePath);
    }
}
