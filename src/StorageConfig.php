<?php

namespace Ozerich\FileStorage;

use Illuminate\Support\ServiceProvider;
use Ozerich\FileStorage\Commands\RegenerateThumbnailsCommand;
use Ozerich\FileStorage\Repositories\FileRepository;
use Ozerich\FileStorage\Repositories\IFileRepository;
use Ozerich\FileStorage\Services\TempFile;
use Ozerich\FileStorage\Structures\Scenario;

class StorageConfig
{
    private function config($param = '')
    {
        return \config()->get('filestorage' . (empty($param) ? '' : '.' . $param));
    }

    public function getDefaultScenario()
    {
        $defaultStorage = $this->config('defaultStorage');
        if (!$defaultStorage) {
            return null;
        }

        $defaultValidator = $this->config('defaultValidator');

        return new Scenario(null, [
            'storage' => $defaultStorage,
            'validator' => $defaultValidator
        ]);
    }

    public function getScenarioByName($scenarioName = null)
    {
        if (empty($scenarioName)) {
            return $this->getDefaultScenario();
        }
        
        $config = $this->config();

        if (!$config || !isset($config['scenarios']) || !isset($config['scenarios'][$scenarioName])) {
            return null;
        }

        return new Scenario($scenarioName, $config['scenarios'][$scenarioName]);
    }
}
