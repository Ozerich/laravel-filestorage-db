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
    protected $name = 'filestorage:regenerate-thumbnails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate all thumbnails for images';

    public function handle(FileRepository $fileRepository)
    {
        $files = $fileRepository->all()->reverse();

        foreach ($files as $ind => $file) {
            echo 'File ' . ($ind + 1) . ' / ' . count($files) . ': ID ' . $file->id . ' ---- ';
            Storage::staticPrepareThumbnails($file, null, true);
            echo "OK\n";
        }
    }
}
