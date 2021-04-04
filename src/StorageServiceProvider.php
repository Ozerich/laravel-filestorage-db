<?php

namespace Ozerich\FileStorage;

use Illuminate\Support\ServiceProvider;
use Ozerich\FileStorage\Commands\RegenerateThumbnailsCommand;
use Ozerich\FileStorage\Repositories\FileRepository;
use Ozerich\FileStorage\Repositories\IFileRepository;
use Ozerich\FileStorage\Services\TempFile;

class StorageServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        TempFile::setTmpFolder(storage_path());

        $this->loadMigrationsFrom(__DIR__ . '/../migrations');

        $this->publishes([
            __DIR__ . '/../config.php' => config_path('filestorage.php'),
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([
                RegenerateThumbnailsCommand::class,
            ]);
        }

        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'filestorage');
    }
}
