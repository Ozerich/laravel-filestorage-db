<?php

namespace Ozerich\FileStorage;

use Illuminate\Support\ServiceProvider;
use Ozerich\FileStorage\Commands\RegenerateThumbnailsCommand;
use Ozerich\FileStorage\Exceptions\InvalidScenarioException;
use Ozerich\FileStorage\Repositories\FileRepository;
use Ozerich\FileStorage\Repositories\IFileRepository;
use Ozerich\FileStorage\Services\TempFile;
use Ozerich\FileStorage\Structures\Scenario;

class StorageConfig
{
    private function config($param = '', $default = null)
    {
        return \config()->get('filestorage' . (empty($param) ? '' : '.' . $param), $default);
    }

    public function getDefaultValidatorConfig()
    {
        return $this->config('defaultValidator', null);
    }

    public function getDefaultImageQuality()
    {
        return $this->config('defaultImageQuality', null);
    }

    public function getDefaultScenario()
    {
        $defaultStorage = $this->config('defaultStorage');
        if (!$defaultStorage) {
            return null;
        }

        $defaultValidator = $this->getDefaultValidatorConfig();

        return new Scenario(null, [
            'storage' => $defaultStorage,
            'validator' => $defaultValidator
        ]);
    }

    /**
     * @param null $scenarioName
     * @return Scenario|null
     * @throws Exceptions\InvalidConfigException
     */
    public function getScenarioByName($scenarioName = null)
    {
        if (empty($scenarioName)) {
            return $this->getDefaultScenario();
        }

        $config = $this->config();

        if (!$config || !isset($config['scenarios']) || !isset($config['scenarios'][$scenarioName])) {
            return null;
        }

        $scenarioConfig = $config['scenarios'][$scenarioName];

        if (!isset($scenarioConfig['validator'])) {
            $scenarioConfig['validator'] = $this->getDefaultValidatorConfig();
        }

        return new Scenario($scenarioName, $scenarioConfig);
    }
}
