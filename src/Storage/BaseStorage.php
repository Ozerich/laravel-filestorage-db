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

    abstract function exists(string $filename): bool;

    abstract function upload(string $src, string $dest, bool $deleteSrc = false): bool;

    abstract function download(string $dest, string $filename): bool;

    abstract function delete(string $filename): bool;

    abstract function getUrl(string $filename): string;

    abstract function getBody(string $filename): ?string;
}
