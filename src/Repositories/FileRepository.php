<?php

namespace Ozerich\FileStorage\Repositories;

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

    public function find(int|string $id): ?File
    {
        if (is_numeric($id)) {
            return $this->findById($id);
        }

        return $this->model::query()->where('uuid', '=', $id)->first();
    }

    /**
     * @param $id
     * @return File
     */
    public function findById($id): ?File
    {
        return $this->model::query()->where('id', '=', $id)->first();
    }

    /**
     * Returns all the records.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function all()
    {
        return $this->model->all();
    }
}
