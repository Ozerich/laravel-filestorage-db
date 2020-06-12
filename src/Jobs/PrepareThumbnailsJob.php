<?php

namespace Ozerich\FileStorage\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Ozerich\FileStorage\Models\File;
use Ozerich\FileStorage\Storage;
use Ozerich\FileStorage\Structures\Scenario;

class PrepareThumbnailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
