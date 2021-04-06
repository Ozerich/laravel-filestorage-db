<?php

namespace Ozerich\FileStorage\Repositories;

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

    public function find($id): ?File
    {
        if (is_numeric($id)) {
            return $this->findById($id);
        }

        return $this->model::query()->where('uuid', '=', $id)->first();
    }

    public function findById($id): ?File
    {
        return $this->model::query()->where('id', '=', $id)->first();
    }

    public function all(): Collection
    {
        return $this->model->all();
    }
}
