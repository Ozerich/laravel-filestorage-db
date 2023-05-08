<?php

namespace Ozerich\FileStorage\Commands;

use Illuminate\Console\Command;
use Ozerich\FileStorage\Repositories\FileRepository;
use Ozerich\FileStorage\Storage;

class RegenerateThumbnailsCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'filestorage:regenerate-thumbnails {file_id=0}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate all thumbnails for images';

    public function handle(FileRepository $fileRepository)
    {
        $fileId = $this->argument('file_id');

        $files = $fileRepository->all()->reverse();

        foreach ($files as $ind => $file) {
            echo 'File ' . ($ind + 1) . ' / ' . count($files) . ': ID ' . $file->id . ' ---- ';

            if ($fileId && $file->id != $fileId) {
                echo "Skip\n";
                continue;
            }

            Storage::staticPrepareThumbnails($file, null);
            echo "OK\n";
        }
    }
}
