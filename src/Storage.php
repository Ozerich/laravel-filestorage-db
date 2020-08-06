<?php

namespace Ozerich\FileStorage;

use Illuminate\Http\Request;
use Ozerich\FileStorage\Jobs\PrepareThumbnailsJob;
use Ozerich\FileStorage\Models\File;
use Ozerich\FileStorage\Repositories\FileRepository;
use Ozerich\FileStorage\Services\ImageService;
use Ozerich\FileStorage\Services\ProcessImage;
use Ozerich\FileStorage\Services\Random;
use Ozerich\FileStorage\Services\TempFile;
use Ozerich\FileStorage\Structures\Scenario;
use Ozerich\FileStorage\Structures\Thumbnail;
use Ozerich\FileStorage\Utils\DownloadFile;

class Storage
{
    private $config;

    private $uploadError = null;

    public function __construct()
    {
        $this->config = new StorageConfig();
    }

    public static function getScenario($scenario)
    {
        return (new StorageConfig())->getScenarioByName($scenario);
    }

    public function getUploadError()
    {
        return $this->uploadError;
    }

    public function createFromUrl($url, $scenario = null)
    {
        if (!empty($scenario)) {
            $scenarioInstance = $this->config->getScenarioByName($scenario);
            if (!$scenario) {
                $this->uploadError = 'Invalid scenario';
                return null;
            }
        } else {
            $scenarioInstance = $this->config->getDefaultScenario();
            if (!$scenarioInstance) {
                $this->uploadError = 'Cannot create default scenario, it seems that defaultStorage is not set in config filestorage.php';
                return null;
            }
        }

        $p = strrpos($url, '?');
        if ($p !== false) {
            $url_without_params = substr($url, 0, $p);
        } else {
            $url_without_params = $url;
        }

        $file_name = $file_ext = null;

        $p = strrpos($url_without_params, '.');
        if ($p !== null) {
            $file_ext = substr($url_without_params, $p + 1);

            $p = strrpos($url_without_params, '/');
            if ($p !== false) {
                $file_name = substr($url_without_params, $p + 1);
            }
        }

        if (strlen($file_ext) > 4) {
            $file_ext = null;
        }

        $temp = new TempFile();

        try {
            DownloadFile::download($url, $temp->getPath());
        } catch (\Exception $ex) {
            return null;
        }

        if (!$file_ext) {
            $mime = mime_content_type($temp->getPath());
            $file_ext = ImageService::mime2ext($mime);
        }

        return $this->createFile($temp->getPath(), $file_name, $file_ext, $scenarioInstance);
    }

    public function createFromRequest($scenario = null, $requestFieldName = 'file')
    {
        /** @var Request $request */
        $request = app()->request;

        $file = $request->file($requestFieldName);
        if (!$file) {
            $this->uploadError = 'File is empty';
            return null;
        }

        if (!empty($scenario)) {
            $scenarioInstance = $this->config->getScenarioByName($scenario);
            if (!$scenario) {
                $this->uploadError = 'Invalid scenario';
                return null;
            }
        } else {
            $scenarioInstance = $this->config->getDefaultScenario();
            if (!$scenarioInstance) {
                $this->uploadError = 'Cannot create default scenario, it seems that defaultStorage is not set in config filestorage.php';
                return null;
            }
        }

        return $this->createFile(
            $file->getPathName(),
            $file->getClientOriginalName(),
            $file->getClientOriginalExtension(),
            $scenarioInstance
        );
    }

    private function createFile($file_path, $file_name, $file_ext, Scenario $scenario)
    {
        $temp = new TempFile($file_ext);
        $temp->from($file_path);

        $validator = $scenario->getValidator();
        if ($validator) {
            $validate = $scenario->getValidator()->validate($file_path, $file_name);
            if (!$validate) {
                $errors = $scenario->getValidator()->getErrors();
                $this->uploadError = array_shift($errors);
                return null;
            }
        }

        try {
            if ($scenario->shouldFixOrientation()) {
                $processImage = new ProcessImage($file_path);
                $processImage->fixOrientation();
            }
        } catch (\Exception $exception) {

        }

        $file_ext = strtolower($file_ext);
        $file_hash = Random::GetString();

        $scenario->getStorage()->upload($file_path, $file_hash, $file_ext);
        $model = $this->createModel($temp->getPath(), $file_hash, $file_name, $file_ext, $scenario);

        if (!$model) {
            return null;
        }

        if ($scenario->hasThumnbails()) {
            dispatch(new PrepareThumbnailsJob($model));
        }

        return $model;
    }

    /**
     * @param $file_path
     * @param $file_hash
     * @param $file_ext
     * @param Scenario $scenario
     * @return File
     */
    private function createModel($file_path, $file_hash, $file_name, $file_ext, Scenario $scenario)
    {
        try {
            $file_info = ImageService::getImageInfo($file_path);
        } catch (\Exception $exception) {

        }

        $model = new File();

        $model->hash = $file_hash;
        $model->name = $file_name;
        $model->scenario = $scenario->getId();
        $model->width = $file_info['width'] ?? null;
        $model->height = $file_info['height'] ?? null;
        $model->size = filesize($file_path);
        $model->mime = mime_content_type($file_path);
        $model->ext = strtolower($file_ext);

        $model->save();

        return $model;
    }


    public static function staticDeleteThumbnails(File $file, ?Thumbnail $thumbnail = null)
    {
        $scenario = (new StorageConfig())->getScenarioByName($file->scenario);
        $scenario->getStorage()->deleteAllThumbnails($file->hash);
    }

    public static function staticPrepareThumbnails(File $file, ?Thumbnail $thumbnail = null, $forceRegenerate = false)
    {
        if ($forceRegenerate) {
            self::staticDeleteThumbnails($file, $thumbnail);
        }

        return ImageService::prepareThumbnails($file, (new StorageConfig())->getScenarioByName($file->scenario), $thumbnail);
    }

    public function setFileScenario($fileId, $scenario)
    {
        $repository = new FileRepository(new File());

        /** @var File $model */
        $model = $repository->find($fileId);
        if (!$model) {
            return null;
        }

        $model->setScenario($scenario, true);
        return $model;
    }
}
