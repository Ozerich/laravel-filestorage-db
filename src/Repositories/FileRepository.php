<?php

namespace Ozerich\FileStorage\Repositories;

use Illuminate\Database\Eloquent\Model;
use Ozerich\FileStorage\Models\File;

class FileRepository implements IFileRepository
{
    /**
     * @var Model
     */
    protected $model;

    public function __construct(File $model)
    {
        $this->model = $model;
    }

    /**
     * @param $id
     * @return File
     */
    public function find($id): ?File
    {
        return $this->model->find($id);
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
