<?php

namespace Ozerich\FileStorage;

use Illuminate\Http\Request;
use Ozerich\FileStorage\Jobs\PrepareThumbnailsJob;
use Ozerich\FileStorage\Models\File;
use Ozerich\FileStorage\Services\ImageService;
use Ozerich\FileStorage\Services\ProcessImage;
use Ozerich\FileStorage\Services\Random;
use Ozerich\FileStorage\Services\TempFile;
use Ozerich\FileStorage\Structures\Scenario;
use Ozerich\FileStorage\Structures\Thumbnail;

class Storage
{
    public static function GetScenario($scenarioName)
    {
        $config = config()->get('filestorage');

        if (!$config || !isset($config['scenarios']) || !isset($config['scenarios'][$scenarioName])) {
            return null;
        }

        return new Scenario($scenarioName, $config['scenarios'][$scenarioName]);
    }

    public function createFromRequest($scenario)
    {
        $scenarioInstance = self::GetScenario($scenario);

        /** @var Request $request */
        $request = app()->request;

        $file = $request->file('file');
        if (!$file) {
            abort('400', 'File is empty');
        }

        return $this->createFile($file->getPathName(), $file->getClientOriginalName(), $file->getClientOriginalExtension(), $scenarioInstance);
    }

    private function createFile($file_path, $file_name, $file_ext, Scenario $scenario)
    {
        $temp = new TempFile($file_ext);
        $temp->from($file_path);

        $validator = $scenario->getValidator();
        if ($validator) {
            $validate = $scenario->getValidator()->validate($file_path, $file_name);
            if (!$validate) {
                $this->errors = $scenario->getValidator()->getErrors();
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


        $this->errors = [];

        $file_ext = strtolower($file_ext);
        $file_hash = Random::GetString();

        $scenario->getStorage()->upload($file_path, $file_hash, $file_ext);
        $model = $this->createModel($temp->getPath(), $file_hash, $file_name, $file_ext, $scenario);

        if (!$model) {
            return null;
        }

        dispatch(new PrepareThumbnailsJob($model));

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
        $scenario = self::getScenario($file->scenario);
        $scenario->getStorage()->deleteAllThumbnails($file->hash);
    }

    public static function staticPrepareThumbnails(File $file, ?Thumbnail $thumbnail = null, $forceRegenerate = false)
    {
        if ($forceRegenerate) {
            self::staticDeleteThumbnails($file, $thumbnail);
        }

        return ImageService::prepareThumbnails($file, self::getScenario($file->scenario), $thumbnail);
    }

}
