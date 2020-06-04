<?php

namespace Ozerich\FileStorage\Jobs;

use Ozerich\FileStorage\Models\File;
use Ozerich\FileStorage\Storage;
use OZiTAG\Tager\Backend\Core\QueueJob;

class PrepareThumbnailsJob extends QueueJob
{
    private $file;

    public function __construct(File $file)
    {
        $this->file = $file;
    }

    public function handle()
    {
        Storage::staticPrepareThumbnails($this->file);
    }
}
