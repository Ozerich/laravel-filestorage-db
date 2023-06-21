<?php

namespace Ozerich\FileStorage\Storage;

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

    abstract function exists(string $filename, string $hash): bool;

    abstract function upload(string $src, string $dest, string $hash, bool $deleteSrc = false): bool;

    abstract function download(string $filename, string $hash, string $dest): bool;

    abstract function delete(string $filename, string $hash): bool;

    abstract function getUrl(string $filename, string $hash): string;

    abstract function getBody(string $filename, string $hash): ?string;
}
