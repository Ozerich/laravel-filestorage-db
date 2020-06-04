<?php

namespace Ozerich\FileStorage;

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
        //
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
