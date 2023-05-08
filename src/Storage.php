<?php

namespace Ozerich\FileStorage;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Ozerich\FileStorage\Utils\FileNameHelper;
use Ramsey\Uuid\Uuid;
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

    public static function getScenario($scenario, $returnDefaultIfNotFound = false)
    {
        $config = new StorageConfig();

        $result = $config->getScenarioByName($scenario);
        if (!$result && $returnDefaultIfNotFound) {
            $result = $config->getDefaultScenario();
        }

        return $result;
    }

    public function getUploadError()
    {
        return $this->uploadError;
    }

    public function createFromLocalFile($path, $scenario = null, $filename = null, $generateThumbnails = false, $deleteFile = false)
    {
        if (!empty($scenario)) {
            $scenarioInstance = $this->config->getScenarioByName($scenario);
            if (!$scenarioInstance) {
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

        if (is_null($filename)) {
            $slashPos = mb_strrpos($path, '/');
            if ($slashPos === false) {
                return null;
            }
            $filename = mb_substr($path, $slashPos + 1);
        }

        $p = mb_strrpos($filename, '.');
        $fileExt = $p === false ? null : mb_substr($filename, $p + 1);

        $model = $this->createFile($path, $filename, $fileExt, $scenarioInstance, $generateThumbnails);

        if ($deleteFile) {
            @unlink($path);
        }

        return $model;
    }

    public function createFromUrl($url, $scenario = null, $generateThumbnails = true)
    {
        if (!empty($scenario)) {
            $scenarioInstance = $this->config->getScenarioByName($scenario);
            if (!$scenarioInstance) {
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

        if (preg_match('#https://drive.google.com/file/d/(.+?)/view#si', $url, $urlPreg)) {
            $url = 'https://drive.google.com/u/0/uc?id=' . $urlPreg[1] . '&export=download';
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
            $this->uploadError = $ex->getMessage();
            return null;
        }

        if (!$file_ext) {
            $mime = mime_content_type($temp->getPath());
            $file_ext = ImageService::mime2ext($mime);
        }

        return $this->createFile($temp->getPath(), $file_name, $file_ext, $scenarioInstance, $generateThumbnails);
    }

    public function createFromRaw($rawData, $fileName, $scenario = null, $generateThumbnails = true)
    {
        if (!empty($scenario)) {
            $scenarioInstance = $this->config->getScenarioByName($scenario);
            if (!$scenarioInstance) {
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

        $file_ext = null;
        if (!empty($fileName)) {
            $file_ext_data = explode('.', $fileName);
            if (count($file_ext_data) > 1) {
                $file_ext = $file_ext_data[count($file_ext_data) - 1];
            }
        }

        $temp = new TempFile();
        $temp->write($rawData);

        return $this->createFile($temp->getPath(), $fileName, $file_ext, $scenarioInstance, $generateThumbnails);
    }

    public function createFromBase64($base64Data, $fileName, $scenario = null, $generateThumbnails = true)
    {
        if (!empty($scenario)) {
            $scenarioInstance = $this->config->getScenarioByName($scenario);
            if (!$scenarioInstance) {
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

        if (strpos($base64Data, ';') !== false) {
            list($meta, $content) = explode(';', $base64Data);
        } else {
            $meta = null;
            $content = $base64Data;
        }

        $p = strpos($content, ',');
        if ($p !== false) {
            $content = substr($content, $p + 1);
        }

        $image_raw = base64_decode($content);
        $file_ext = null;

        if (!empty($fileName)) {
            $file_ext_data = explode('.', $fileName);
            if (count($file_ext_data) > 1) {
                $file_ext = $file_ext_data[count($file_ext_data) - 1];
            }
        } else if ($meta) {
            $mime_type = substr($meta, 5);
            $file_ext = ImageService::mime2ext($mime_type);
        }

        $temp = new TempFile();
        $temp->write($image_raw);

        return $this->createFile($temp->getPath(), $fileName, $file_ext, $scenarioInstance, $generateThumbnails);
    }

    public function createFromRequest($scenario = null, $requestFieldName = 'file', $generateThumbnails = true)
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
            if (!$scenarioInstance) {
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
            $scenarioInstance,
            $generateThumbnails
        );
    }

    private function createFile($file_path, $file_name, $file_ext, Scenario $scenario, $generateThumbnails = true)
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
        if ($file_ext == 'svg') {
            $processImage = new ProcessImage($file_path);
            $processImage->fixSvg();
        }

        $file_hash = Random::GetString();

        if ($scenario->shouldSaveOriginalFilename()) {
            $ind = 0;
            $baseFilename = $file_name;
            $baseFilenamePointPos = strrpos($baseFilename, '.');
            $baseFilenameWithoutExt = $baseFilenamePointPos !== null ? substr($baseFilename, 0, $baseFilenamePointPos) : $baseFilename;

            while (true) {
                if ($scenario->shouldReplaceFileIfExists() || $scenario->getStorage()->exists($file_hash, $file_ext, null, false, $file_name) == false) {
                    break;
                }
                $ind = $ind + 1;
                $file_name = $baseFilenameWithoutExt . '(' . $ind . ').' . $file_ext;
            }
        }

        $scenario->getStorage()->upload(
            $file_path,
            FileNameHelper::get($file_hash, $file_ext, null, false, $scenario->shouldSaveOriginalFilename() ? $file_name : null),
        );

        $model = $this->createModel($temp->getPath(), $file_hash, $file_name, $file_ext, $scenario);

        if (!$model) {
            return null;
        }

        if ($generateThumbnails && $scenario->hasThumnbails()) {
            dispatch(new PrepareThumbnailsJob($model->id));
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

        $model->uuid = Uuid::uuid4();
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
        $thumbnails = $file->thumbnails ? json_decode($file->thumbnails, true) : [];
        if (empty($thumbnails)) {
            return;
        }

        $scenario = (new StorageConfig())->getScenarioByName($file->scenario);

        if ($scenario) {
            foreach ($thumbnails as $thumbnail) {
                $extParts = explode(':', $thumbnail);
                if (count($extParts) > 1) {
                    $ext = $extParts[1];
                    $filename = $extParts[0];
                } else {
                    $ext = $file->ext;
                    $filename = $extParts[0];
                }

                $is2x = false;
                if (str_ends_with($thumbnail, '@2x')) {
                    $thumbnail = substr(0, strlen($thumbnail) - 3);
                    $is2x = true;
                }

                list ($width, $height) = explode('x', $thumbnail);

                $scenario->getStorage()->delete(FileNameHelper::getForSize(
                    $file->hash, $ext,
                    $width == 'AUTO' ? null : intval($width), $height == 'AUTO' ? null : intval($height),
                    $is2x, $scenario->shouldSaveOriginalFilename() ? $file->name : null
                ));

                $file->removeThumbnail($thumbnail);
            }

            $file->save();
        }
    }

    public static function checkThumbnails(File $file)
    {
        $scenario = $file->scenarioInstance();

        $thumbMap = [];
        $requiredThumbnails = [];
        foreach ($scenario->getThumbnails() as $thumbName => $thumbnail) {
            $thumbMap[$thumbName] = [];
            
            $requiredThumbnails[] = $thumbnail->getDatabaseValue(false, false);
            $thumbMap[$thumbName][$thumbnail->getDatabaseValue(false, false)] = 0;
            if ($thumbnail->is2xSupport()) {
                $requiredThumbnails[] = $thumbnail->getDatabaseValue(true, false);
                $thumbMap[$thumbName][$thumbnail->getDatabaseValue(true, false)] = 0;
            }
            if ($thumbnail->isWebpSupport()) {
                $requiredThumbnails[] = $thumbnail->getDatabaseValue(false, true);
                $thumbMap[$thumbName][$thumbnail->getDatabaseValue(false, true)] = 0;
                if ($thumbnail->is2xSupport()) {
                    $requiredThumbnails[] = $thumbnail->getDatabaseValue(true, true);
                    $thumbMap[$thumbName][$thumbnail->getDatabaseValue(true, true)] = 0;
                }
            }
        }

        $currentThumbnails = $file->thumbnails ? json_decode($file->thumbnails, true) : [];
        foreach ($currentThumbnails as $currentThumbnail) {

            if (!in_array($currentThumbnail, $requiredThumbnails)) {
                $extParts = explode(':', $currentThumbnail);
                if (count($extParts) > 1) {
                    $ext = $extParts[1];
                    $filename = $extParts[0];
                } else {
                    $ext = $file->ext;
                    $filename = $extParts[0];
                }

                $is2x = false;
                if (str_ends_with($filename, '@2x')) {
                    $thumbnail = substr(0, strlen($filename) - 3);
                    $is2x = true;
                }

                list ($width, $height) = explode('x', $filename);

                $scenario->getStorage()->delete(FileNameHelper::getForSize(
                    $file->hash, $ext,
                    $width == 'AUTO' ? null : intval($width), $height == 'AUTO' ? null : intval($height),
                    $is2x, $scenario->shouldSaveOriginalFilename() ? $file->name : null
                ));

                $file->removeThumbnail($currentThumbnail)->save();
            }

            foreach ($thumbMap as &$thumb) {
                if (array_key_exists($currentThumbnail, $thumb)) {
                    $thumb[$currentThumbnail] = 1;
                }
            }
        }

        foreach ($thumbMap as $thumbName => $thumbData) {
            $foundZero = false;
            foreach ($thumbData as $value) {
                if ($value == 0) {
                    $foundZero = true;
                    break;
                }
            }

            if ($foundZero) {
                Storage::staticPrepareThumbnails($file, $scenario->getThumbnailByAlias($thumbName));
            }
        }
    }

    public static function staticPrepareThumbnails(File $file, ?Thumbnail $thumbnail = null)
    {
        if (!$thumbnail) {
            self::staticDeleteThumbnails($file, $thumbnail);
        }

        $scenario = (new StorageConfig())->getScenarioByName($file->scenario);
        if (!$scenario || !$scenario->hasThumnbails()) {
            return;
        }

        ImageService::prepareThumbnails($file, $scenario, $thumbnail);
    }

    public function setFileScenario(string|int $fileId, ?string $scenario): ?File
    {
        /** @var FileRepository $repository */
        $repository = App::make(FileRepository::class);

        /** @var File $model */
        $model = $repository->find($fileId);
        if (!$model) {
            return null;
        }

        $model->setScenario($scenario, true, true);

        return $model;
    }

    public function sendDownloadFileResponse(File $file, ?string $filename = null)
    {
        $filename = $filename ?? basename($file->name);

        header('Content-Type: ' . $file->mime);
        header("Content-Transfer-Encoding: Binary");
        header("Content-disposition: attachment; filename=\"" . $filename . "\"");

        $tmpFile = new TempFile();
        $tmpFile->write($file->getBody());
        readfile($tmpFile->getPath());
        exit;
    }

    public static function fromUUIDtoId(?string $uuid): ?int
    {
        if (!$uuid) {
            return null;
        }

        /** @var FileRepository $repository */
        $repository = App::make(FileRepository::class);

        $model = $repository->find($uuid);
        if (!$model) {
            return null;
        }

        return $model->id;
    }

    public function clone(int $fileId): ?int
    {
        /** @var FileRepository $repository */
        $repository = App::make(FileRepository::class);

        $file = $repository->findById($fileId);
        if (!$file) {
            return null;
        }

        $fileBody = $file->getBody();
        if (!$fileBody) {
            return null;
        }

        $model = $this->createFromRaw($fileBody, $file->scenario, $file->name, true);

        return $model ? $model->id : null;
    }
}
