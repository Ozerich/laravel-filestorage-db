<?php

namespace Ozerich\FileStorage\Structures;

class Thumbnail
{
    /** @var int */
    private $width;

    /** @var int */
    private $height;

    /** @var bool */
    private $crop;

    /** @var bool */
    private $exact;

    /** @var bool */
    private $is_2x;

    /** @var bool */
    private $is_force;

    /** @var bool */
    private $is_force_2x;

    /** @var bool */
    private $is_webp;

    /**
     * Thumbnail constructor.
     * @param int $width
     * @param int $height
     * @param bool $crop
     * @param bool $exact
     * @param bool $is_2x
     * @param bool $is_force
     * @param bool $is_webp
     * @param bool $is_force_2x
     */
    public function __construct($width = 0, $height = 0, $crop = false, $exact = false, $is_2x = false, $is_force = false, $is_webp = false, $is_force_2x = false)
    {
        $this->width = $width;
        $this->height = $height;
        $this->crop = $crop;
        $this->exact = $exact;
        $this->is_2x = $is_2x;
        $this->is_force = $is_force;
        $this->is_webp = $is_webp;
        $this->is_force_2x = $is_force_2x;
    }

    /**
     * @return int
     */
    public function getWidth()
    {
        return $this->width;
    }

    /**
     * @return int
     */
    public function getHeight()
    {
        return $this->height;
    }

    /**
     * @return bool
     */
    public function getCrop()
    {
        return $this->crop;
    }

    /**
     * @return bool
     */
    public function getExact()
    {
        return $this->exact;
    }

    /**
     * @return string
     */
    public function getFilenamePrefix()
    {
        return ($this->width ? $this->width : 'AUTO') . '_' . ($this->height ? $this->height : 'AUTO');
    }

    /**
     * @return string
     */
    public function getThumbId()
    {
        return ($this->width ? $this->width : 'AUTO') . 'x' . ($this->height ? $this->height : 'AUTO');
    }

    /**
     * @return bool
     */
    public function is2xSupport()
    {
        return $this->is_2x;
    }

    /**
     * @return bool
     */
    public function isForceSize()
    {
        return $this->is_force;
    }

    /**
     * @return bool
     */
    public function isForce2xSize()
    {
        return $this->is_force_2x;
    }

    /**
     * @return bool
     */
    public function isWebpSupport()
    {
        return $this->is_webp;
    }

}
