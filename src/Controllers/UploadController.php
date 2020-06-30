<?php

namespace Ozerich\FileStorage\Controllers;

use Illuminate\Routing\Controller;
use Ozerich\FileStorage\Models\File;
use Ozerich\FileStorage\Storage;

class UploadController extends Controller
{
    /**
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Storage $storage)
    {
        /** @var File $file */
        $file = $storage->createFromRequest();

        if (!$file) {
            abort(400, $storage->getUploadError());
        }

        return $file->getShortJson();
    }
}
