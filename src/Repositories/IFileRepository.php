<?php

namespace Ozerich\FileStorage\Repositories;

interface IFileRepository
{
    public function find($id);

    public function all();
}
