<?php

namespace Ozerich\FileStorage\Repositories;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Ozerich\FileStorage\Models\File;

class FileRepository
{
    /**
     * @var Model
     */
    protected $model;

    public function __construct(File $model)
    {
        $this->model = $model;
    }

    public function builder(): Builder
    {
        return $this->model::query();
    }

    public function find($id): ?File
    {
        if (is_numeric($id)) {
            return $this->findById($id);
        }

        return $this->builder()->where('uuid', '=', $id)->first();
    }

    public function findById($id): ?File
    {
        return $this->builder()->where('id', '=', $id)->first();
    }

    public function all(): Collection
    {
        return $this->model->all();
    }

    public function allWithoutThumbnails(): Collection
    {
        return $this->builder()->whereNull('thumbnails')->get();
    }
}
