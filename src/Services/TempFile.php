<?php

namespace Ozerich\FileStorage\Services;

class TempFile
{
    static $tmpFolder = '/tmp';

    /** @var string */
    private $filename;

    /** @var string */
    private $extension;

    private function generateRandomString($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public static function setTmpFolder($tmpFolder)
    {
        self::$tmpFolder = $tmpFolder;
    }

    /**
     * TempFile constructor.
     * @param string $tmp_folder
     * @param null $file_ext
     */
    public function __construct($file_ext = null)
    {
        $this->extension = $file_ext;
        $this->filename = $this->generateRandomString();
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return self::$tmpFolder . DIRECTORY_SEPARATOR . $this->filename . ($this->extension ? '.' . $this->extension : '');
    }

    public function __destruct()
    {
        @unlink($this->getPath());
    }

    /**
     * @param $content
     */
    public function write($content)
    {
        $f = fopen($this->getPath(), 'w+');
        fwrite($f, $content);
        fclose($f);
    }

    /**
     * @param $file_path
     */
    public function from($file_path)
    {
        copy($file_path, $this->getPath());
    }
}
