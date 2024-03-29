<?php

namespace Ozerich\FileStorage\Storage;

use Aws\S3\S3Client;
use Illuminate\Support\Facades\Request;

class S3Storage extends BaseStorage
{
    protected string $bucket;

    protected string $path;

    protected string $publicUrl;

    protected ?string $acl = null;

    private ?S3Client $s3Client = null;

    private array $s3Config = [];

    public function __construct($config)
    {
        $this->s3Config = [
            'version' => 'latest',
            'region' => $config['region'],
            'credentials' => [
                'key' => $config['accessKey'],
                'secret' => $config['secretKey']
            ],
        ];

        if (!empty($config['host'])) {
            $this->s3Config = array_merge($this->s3Config, [
                'endpoint' => $config['host'],
                'use_path_style_endpoint' => true
            ]);
        }

        parent::__construct($config);
    }

    private function s3Client(): S3Client
    {
        if (!$this->s3Client) {
            $this->s3Client = new S3Client($this->s3Config);
        }

        return $this->s3Client;
    }

    public function exists(string $filename, string $hash): bool
    {
        return $this->s3Client()->doesObjectExistV2($this->bucket, $this->path . '/' . $filename);
    }

    public function upload(string $src, string $dest, string $hash, bool $deleteSrc = false): bool
    {
        $this->s3Client()->putObject([
            'Bucket' => $this->bucket,
            'Key' => $this->path . '/' . $dest,
            'SourceFile' => $src,
            'ACL' => $this->acl,
        ]);

        if ($deleteSrc) {
            @unlink($src);
        }

        return true;
    }

    public function download(string $filename, string $hash, string $dest): bool
    {
        try {
            $file = $this->s3Client()->getObject([
                'Bucket' => $this->bucket,
                'Key' => $this->path . '/' . $filename,
            ]);
        } catch (\Exception $exception) {
            return false;
        }


        $body = $file->get('Body');

        $f = fopen($dest, 'w+');
        stream_copy_to_stream($body->detach(), $f);
        fclose($f);

        return true;
    }

    public function delete(string $fileName, string $hash): bool
    {
        $this->s3Client()->deleteObject([
            'Bucket' => $this->bucket,
            'Key' => $this->path . '/' . $fileName,
        ]);

        return true;
    }

    function getUrl(string $filename, string $hash): string
    {
        return $this->publicUrl . '/' . $this->path . '/' . $filename;
    }

    public function getBody(string $filename, string $hash): ?string
    {
        try {
            $file = $this->s3Client()->getObject([
                'Bucket' => $this->bucket,
                'Key' => $this->path . '/' . $filename,
            ]);
        } catch (\Exception $exception) {
            return false;
        }

        $body = $file->get('Body');

        return (string)$body;
    }
}
