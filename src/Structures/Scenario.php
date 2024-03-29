<?php

namespace Ozerich\FileStorage\Structures;

use Ozerich\FileStorage\Exceptions\InvalidConfigException;
use Ozerich\FileStorage\Exceptions\InvalidThumbnailException;
use Ozerich\FileStorage\Storage;
use Ozerich\FileStorage\Storage\BaseStorage;
use Ozerich\FileStorage\Storage\FileStorage;
use Ozerich\FileStorage\StorageConfig;
use Ozerich\FileStorage\Validators\Validator;

class Scenario
{
    private ?string $id = null;

    private bool $isSingleThumbnail = false;

    private array $thumbnails = [];

    private array $thumbnails_by_alias = [];

    private ?Validator $validator = null;

    private BaseStorage $storage;

    private bool $fixOrientation = true;

    private int $quality = 88;

    private bool $saveOriginalFilename = false;

    private bool $replaceFileIfExists = false;

    private bool $saveFilesAfterDeletion = false;

    /**
     * Scenario constructor.
     * @param $id
     * @param $config
     * @throws InvalidConfigException
     */
    public function __construct($id, $config)
    {
        $this->id = $id;

        if (!isset($config['storage'])) {
            throw new InvalidConfigException('storage is required');
        }
        
        $this->createStorage($config['storage']);

        if (isset($config['validator']) && $config['validator']) {
            $this->validator = $this->createValidator($config['validator']);
        }

        if (isset($config['thumbnail'])) {
            $this->isSingleThumbnail = true;
            $this->setThumbnails(['default' => $config['thumbnail']]);
        } else if (isset($config['thumbnails'])) {
            $this->setThumbnails($config['thumbnails']);
        }

        $this->saveOriginalFilename = $config['saveOriginalFilename'] ?? false;
        $this->replaceFileIfExists = $config['replaceFileIfExists'] ?? false;
        $this->saveFilesAfterDeletion = $config['saveFilesAfterDeletion'] ?? false;

        if (isset($config['fixOrientation'])) {
            $this->fixOrientation = (bool)$config['fixOrientation'];
        }

        if (isset($config['quality']) && $config['quality']) {
            if ($config['quality'] > 0 && $config['quality'] < 1) {
                $this->quality = $config['quality'] * 100;
            } else if ($config['quality'] > 100 || $config['quality'] < 1) {
                throw new InvalidConfigException('Quality is invalid');
            } else {
                $this->quality = $config['quality'];
            }
        } else {
            $defaultQuality = (new StorageConfig())->getDefaultImageQuality();
            if ($defaultQuality) {
                $this->quality = $defaultQuality;
            }
        }
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param array $thumbnails
     */
    private function setThumbnails($thumbnails)
    {
        foreach ($thumbnails as $alias => $thumbnail) {
            $thumbnail_model = new Thumbnail(
                isset($thumbnail['width']) ? $thumbnail['width'] : 0,
                isset($thumbnail['height']) ? $thumbnail['height'] : 0,
                isset($thumbnail['crop']) ? $thumbnail['crop'] : false,
                isset($thumbnail['exact']) ? $thumbnail['exact'] : false,
                isset($thumbnail['2x']) ? $thumbnail['2x'] : false,
                isset($thumbnail['force']) ? $thumbnail['force'] : false,
                isset($thumbnail['webp']) ? $thumbnail['webp'] : false,
                isset($thumbnail['force2x']) ? $thumbnail['force2x'] : false,
            );

            $this->thumbnails[] = $thumbnail_model;

            $this->thumbnails_by_alias[$alias] = $thumbnail_model;
        }
    }

    /**
     * @param $alias
     * @return Thumbnail|null
     */
    public function getThumbnailByAlias($alias)
    {
        if (!isset($this->thumbnails_by_alias[$alias])) {
            throw new InvalidThumbnailException('Thumbnail "' . $alias . '" not found');
        }

        return $this->thumbnails_by_alias[$alias];
    }

    /**
     * @return Thumbnail[]
     */
    public function getThumbnails()
    {
        return $this->thumbnails_by_alias;
    }

    /**
     * @return bool
     */
    public function hasThumnbails()
    {
        return !empty($this->thumbnails);
    }

    /**
     * @param array $config
     * @return Validator
     */
    private function createValidator($config)
    {
        $validator = new Validator();

        if (isset($config['checkExtensionByMimeType'])) {
            $validator->setCheckExtensionByMimeType($config['checkExtensionByMimeType']);
        }

        if (isset($config['extensions'])) {
            $validator->setExtensions($config['extensions']);
        }

        if (isset($config['maxSize'])) {
            $validator->setMaxSize($config['maxSize']);
        }

        return $validator;
    }


    /**
     * @return Validator
     */
    public function getValidator()
    {
        return $this->validator;
    }


    /**
     * @param $config
     * @throws InvalidConfigException
     */
    private function createStorage($config)
    {
        if (isset($config['type'])) {
            if ($config['type'] == 'file') {
                $this->storage = new FileStorage($config);
            } else if ($config['type'] == 's3') {
                $this->storage = new Storage\S3Storage($config);

            }
        } else {
            throw new InvalidConfigException('Invalid storage config for scenario "' . $this->getId() . '": type or class are not set');
        }
    }

    /**
     * @return int
     */
    public function getQuality()
    {
        return $this->quality;
    }

    /**
     * @return BaseStorage
     */
    public function getStorage()
    {
        return $this->storage;
    }

    /**
     * @return bool
     */
    public function shouldFixOrientation()
    {
        return $this->fixOrientation;
    }

    /**
     * @return bool
     */
    public function isSingleThumbnail()
    {
        return $this->isSingleThumbnail;
    }

    public function shouldSaveOriginalFilename(): bool
    {
        return $this->saveOriginalFilename;
    }

    public function shouldSaveFilesAfterDeletion(): bool
    {
        return $this->saveFilesAfterDeletion;
    }

    /**
     * @return bool
     */
    public function shouldReplaceFileIfExists()
    {
        return $this->replaceFileIfExists;
    }
}
