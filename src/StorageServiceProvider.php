<?php

namespace Ozerich\FileStorage;

use Ozerich\FileStorage\Repositories\FileRepository;
use Ozerich\FileStorage\Repositories\IFileRepository;
use Ozerich\FileStorage\Services\TempFile;
use Illuminate\Support\ServiceProvider;

class StorageServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(
            IFileRepository::class,
            FileRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        TempFile::setTmpFolder(storage_path());

        $this->loadMigrationsFrom(__DIR__ . '/../Migrations');

        $this->publishes([
            __DIR__ . '/config.php' => config_path('filestorage.php'),
        ]);
    }
}
