<?php

namespace Ozerich\FileStorage\Rules;

use Illuminate\Contracts\Validation\Rule;
use Ozerich\FileStorage\Models\File;
use Ozerich\FileStorage\Repositories\FileRepository;

class FileRule implements Rule
{
    private $fileRepository;

    public function __construct()
    {
        $this->fileRepository = new FileRepository(new File());
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return $this->fileRepository->find($value) != null;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The :attribute is not valid - File not found';
    }
}
