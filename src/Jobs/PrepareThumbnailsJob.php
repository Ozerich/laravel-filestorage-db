<?php

namespace Ozerich\FileStorage\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Ozerich\FileStorage\Models\File;
use Ozerich\FileStorage\Repositories\FileRepository;
use Ozerich\FileStorage\Storage;
use Ozerich\FileStorage\Structures\Scenario;

class PrepareThumbnailsJob implements ShouldQueue
{
    public function __construct(protected int $id)
    {
    }

    public function handle(FileRepository $fileRepository)
    {
        $model = $fileRepository->findById($this->id);

        if ($model) {
            Storage::staticPrepareThumbnails($model);
        }
    }
}
