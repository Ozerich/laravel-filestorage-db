<?php

namespace Ozerich\FileStorage\Commands;

use Illuminate\Console\Command;
use Ozerich\FileStorage\Models\File;
use Ozerich\FileStorage\Repositories\FileRepository;
use Ozerich\FileStorage\Storage;

class DeleteNotExistedFilesCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'filestorage:delete-not-used-files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete not existed files';

    public function handle(FileRepository $fileRepository)
    {
        $uploadsFolder = realpath(__DIR__ . '/../../../../../storage/app/public/uploads');
        if (!is_dir($uploadsFolder)) {
            $this->error('Uploads folder is not found');
            return;
        }
        $storage = new Storage\FileStorage([
            'uploadDirPath' => $uploadsFolder
        ]);

        echo "Loading all files..." . PHP_EOL;
        $allPathes = $storage->getAllFiles();
        echo 'Found ' . count($allPathes) . ' files';
        $size = 0;
        foreach ($allPathes as $path) {
            $size += filesize($path);
        }
        echo ' (' . ceil($size / 1024 / 1024) . ' mb) on storage' . "\n";

        echo "Loading used files..." . PHP_EOL;
        $usedPathes = [];
        $deletedCount = 0;
        $this->withProgressBar($fileRepository->all(), function (File $file) use (&$usedPathes, &$deletedCount) {
            $scenario = $file->scenarioInstance(false);
            if (!$scenario) {
                return;
            }

            $filePathes = $scenario->getStorage()->getThumbnailPathes($file->hash, $scenario->shouldSaveOriginalFilename());

            $filePath = $file->getPath();
            if ($filePath) {
                $filePathes[] = $filePath;
            } else {
                $file->delete();
                $deletedCount++;
            }

            $usedPathes = array_merge($usedPathes, $filePathes);
        });
        $usedPathes = array_values(array_unique($usedPathes));
        echo PHP_EOL . 'Found ' . count($usedPathes) . ' used files on storage, deleted ' . $deletedCount;

        echo PHP_EOL . "Find files to delete..." . PHP_EOL;
        $toDelete = [];
        $this->withProgressBar($allPathes, function ($path) use (&$toDelete, $usedPathes) {
            if (!in_array($path, $usedPathes)) {
                $toDelete[] = $path;
            }
        });
        echo PHP_EOL . 'Found ' . count($toDelete) . ' files to delete';

        if (count($toDelete) > 0) {
            echo PHP_EOL . "Delete files..." . PHP_EOL;
            $removedCount = 0;
            $this->withProgressBar($toDelete, function ($toDeleteFile) use (&$removedCount, &$storage) {
                if ($storage->removeByPath($toDeleteFile)) {
                    $removedCount++;
                }
            });
            echo PHP_EOL . 'Removed ' . $removedCount . ' files from storage';

            echo PHP_EOL . "Removing empty subfolders...";
            $storage->removeEmptyFolders();
            echo "OK";

            echo PHP_EOL . 'Summary: ';
            $allPathes = $storage->getAllFiles();
            echo 'Found ' . count($allPathes) . ' files';
            $size = 0;
            foreach ($allPathes as $path) {
                $size += filesize($path);
            }
            echo ' (' . ceil($size / 1024 / 1024) . ' mb) on storage';
        }

        echo PHP_EOL;
    }
}
