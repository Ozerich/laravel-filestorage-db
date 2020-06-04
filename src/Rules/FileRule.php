<?php

namespace Ozerich\FileStorage\Rules;

use App\Storage\Models\File;
use App\Storage\Repositories\FileRepository;
use App\Storage\Repositories\IFileRepository;
use Illuminate\Contracts\Validation\Rule;

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
