<?php

namespace Ozerich\FileStorage\Services;

class ResizeImage
{
    // *** Class variables
    private $image;
    private $width;
    private $height;
    private $imageResized;

    private $image_type;
    private $exif;

    function __construct($fileName)
    {
        $image_info = getimagesize($fileName);
        $this->image_type = isset($image_info[2]) ? $image_info[2] : null;

        $imageType = exif_imagetype($fileName);
        if (in_array($imageType, array(IMAGETYPE_JPEG, IMAGETYPE_TIFF_II, IMAGETYPE_TIFF_MM))) {
            if ($exifData = @exif_read_data($fileName, null, true, false)) {
                $this->exif = $exifData;
            }
        }

        // *** Open up the file
        $this->image = $this->openImage($fileName);

        if (!$this->image) {
            $this->setError('Invalid image');
            return;
        }

        // *** Get width and height
        $this->width = imagesx($this->image);
        $this->height = imagesy($this->image);

        $this->imageResized = $this->image;
    }

    public function getWidth()
    {
        return $this->width;
    }

    public function getHeight()
    {
        return $this->height;
    }

    function __destruct()
    {
        if ($this->imageResized) {
            imagedestroy($this->imageResized);
        }
    }

    private $error = null;

    private function setError($error)
    {
        $this->error = $error;
    }

    public function isValid()
    {
        return $this->error === null;
    }

    ## --------------------------------------------------------

    private function openImage($file)
    {
        if ($this->image_type == IMAGETYPE_JPEG) {
            return imagecreatefromjpeg($file);
        } elseif ($this->image_type == IMAGETYPE_GIF) {
            return imagecreatefromgif($file);
        } elseif ($this->image_type == IMAGETYPE_PNG) {
            return imagecreatefrompng($file);
        }

        return null;
    }

    private function initCanvas($width, $height)
    {
        $this->imageResized = imagecreatetruecolor($width, $height);
        imagealphablending($this->imageResized, false);
        imagesavealpha($this->imageResized, true);

        if ($this->image_type == IMAGETYPE_PNG) {
            $bgColor = imagecolorallocatealpha($this->imageResized, 0, 0, 0, 127);
        } else {
            $bgColor = imagecolorallocate($this->imageResized, 255, 255, 255);
        }


        imagefill($this->imageResized, 0, 0, $bgColor);

        return $this->imageResized;
    }

    ## --------------------------------------------------------

    public function resizeImage($newWidth, $newHeight, $option = "auto", $forceSize = false)
    {
        if (!$this->image) return;

        $optionArray = $this->getDimensions($newWidth, $newHeight, $option, $forceSize);
        list ($optimalWidth, $optimalHeight) = $optionArray;

        $this->initCanvas($optimalWidth, $optimalHeight);
        imagecopyresampled($this->imageResized, $this->image, 0, 0, 0, 0, $optimalWidth, $optimalHeight, $this->width, $this->height);

        if (($option == 'auto' && (!$newWidth || !$newHeight)) || ($option != 'auto' && ($newWidth || $newHeight))) {
            $this->crop($optimalWidth, $optimalHeight, $newWidth, $newHeight, $forceSize, $option == 'auto');
        }
    }

    ## --------------------------------------------------------

    private function getDimensions($newWidth, $newHeight, $option, $forceSize = false)
    {
        if ($newWidth == 0) {
            $option = 'portrait';
        }
        if ($newHeight == 0) {
            $option = 'landscape';
        }

        switch ($option) {
            case 'exact':
                $optimalWidth = $newWidth;
                $optimalHeight = $newHeight;
                break;
            case 'portrait':
                $optimalWidth = $this->getSizeByFixedHeight($newHeight);
                $optimalHeight = $newHeight;
                break;
            case 'landscape':
                $optimalWidth = $newWidth;
                $optimalHeight = $this->getSizeByFixedWidth($newWidth);
                break;
            case 'auto':
                $optionArray = $this->getSizeByAuto($newWidth, $newHeight, $forceSize);
                $optimalWidth = $optionArray['optimalWidth'];
                $optimalHeight = $optionArray['optimalHeight'];
                break;
            case 'crop':
                $optionArray = $this->getOptimalCrop($newWidth, $newHeight);
                $optimalWidth = $optionArray['optimalWidth'];
                $optimalHeight = $optionArray['optimalHeight'];
                break;
        }


        if ($forceSize == false) {
            if ($optimalHeight > $this->height) {
                $optimalWidth /= ($optimalHeight / $this->height);
                $optimalHeight = $this->height;
            }

            if ($optimalWidth > $this->width) {
                $optimalHeight /= ($optimalWidth / $this->width);
                $optimalWidth = $this->width;
            }
        }

        return [ceil($optimalWidth), ceil($optimalHeight)];
    }

    ## --------------------------------------------------------

    private function getSizeByFixedHeight($newHeight)
    {
        $ratio = $this->width / $this->height;
        $newWidth = $newHeight * $ratio;
        return $newWidth;
    }

    private function getSizeByFixedWidth($newWidth)
    {
        $ratio = $this->height / $this->width;
        $newHeight = $newWidth * $ratio;
        return $newHeight;
    }

    private function getSizeByAuto($newWidth, $newHeight, $forceSize = true)
    {
        $optimalWidth = $newWidth;
        $optimalHeight = $newHeight;

        $widthK = $newWidth / $this->width;
        $heightK = $newHeight / $this->height;

        if ($forceSize) {
            if ($widthK > $heightK) {
                $optimalWidth = $newWidth;
                $optimalHeight = $this->getSizeByFixedWidth($newWidth);
            } elseif ($widthK < $heightK) {
                $optimalWidth = $this->getSizeByFixedHeight($newHeight);
                $optimalHeight = $newHeight;
            } else {
                $optimalWidth = $newWidth;
                $optimalHeight = $newHeight;
            }
        } else {
            if ($newWidth > $this->width && $newHeight > $this->height) {
                $optimalWidth = $this->width;
                $optimalHeight = $this->height;
            } else if ($newWidth < $this->width && $newHeight < $this->height) {
                return $this->getSizeByAuto($newWidth, $newHeight, true);
            } else if ($heightK < 1) {
                $optimalHeight = $newHeight;
                $optimalWidth = $this->width * $heightK;
            } else if ($widthK < 1) {
                $optimalWidth = $newWidth;
                $optimalHeight = $this->height * $widthK;
            }
        }

        return array('optimalWidth' => $optimalWidth, 'optimalHeight' => $optimalHeight);
    }

    ## --------------------------------------------------------

    private function getOptimalCrop($newWidth, $newHeight)
    {
        $heightRatio = $newHeight < $this->height ? $this->height / $newHeight : 1;
        $widthRatio = $newWidth < $this->width ? $this->width / $newWidth : 1;

        if ($heightRatio < $widthRatio) {
            $optimalRatio = $heightRatio;
        } else {
            $optimalRatio = $widthRatio;
        }

        $optimalHeight = $this->height / $optimalRatio;
        $optimalWidth = $this->width / $optimalRatio;

        return array('optimalWidth' => $optimalWidth, 'optimalHeight' => $optimalHeight);
    }

    ## --------------------------------------------------------
    private function crop($optimalWidth, $optimalHeight, $newWidth, $newHeight, $forceSize = false, $startFromZero = false)
    {
        if ($forceSize == false) {
            if ($optimalWidth < $newWidth && $optimalHeight < $newHeight) {
                return;
            }

            if (!$newWidth) {
                $newWidth = $this->getWidth() * ($newHeight / $this->height);
            } else if (!$newHeight) {
                $newHeight = $this->getHeight() * ($newWidth / $this->width);
            } else {
                if ($newHeight > $this->height) {
                    $newWidth /= ($newHeight / $this->height);
                    $newHeight = $this->height;
                } else if ($newWidth > $this->width) {
                    $newHeight /= ($newWidth / $this->width);
                    $newWidth = $this->width;
                }
            }
        }

        if ($startFromZero) {
            $cropStartX = 0;
            $cropStartY = 0;
        } else {
            $cropStartX = max(0, ((int)$optimalWidth - $newWidth) / 2);
            $cropStartY = max(0, ((int)$optimalHeight - $newHeight) / 2);
        }

        if (!$newWidth) {
            $newWidth = $optimalWidth;
        }

        if (!$newHeight) {
            $newHeight = $optimalHeight;
        }

        $old = $this->imageResized;

        // *** Now crop from center to exact requested size

        $this->imageResized = $this->initCanvas($newWidth, $newHeight);

        $startX = max(0, ($newWidth - $optimalWidth) / 2);
        $startY = max(0, ($newHeight - $optimalHeight) / 2);
        $newWidth = min($newWidth, $optimalWidth);
        $newHeight = min($newHeight, $optimalHeight);

        imagecopyresampled(
            $this->imageResized, $old,
            $startX, $startY,
            $cropStartX, $cropStartY,
            $newWidth, $newHeight,
            $newWidth, $newHeight
        );
    }

    public function saveImage($savePath, $imageQuality = 100)
    {
        if (!$this->image) {
            return;
        }

        switch ($this->image_type) {
            case IMAGETYPE_JPEG:
                if (imagetypes() & IMG_JPG) {
                    imagejpeg($this->imageResized, $savePath, $imageQuality);
                }
                break;

            case IMAGETYPE_GIF:
                if (imagetypes() & IMG_GIF) {
                    imagegif($this->imageResized, $savePath);
                }
                break;

            case IMAGETYPE_PNG:
                $scaleQuality = round(($imageQuality / 100) * 9);
                $invertScaleQuality = 9 - $scaleQuality;

                if (imagetypes() & IMG_PNG) {
                    imagepng($this->imageResized, $savePath);
                }

                break;
        }
    }

    /**
     * @param $savePath
     * @param int $imageQuality
     * @return bool
     */
    public function saveImageAsWebp($savePath, $imageQuality = 100)
    {
        if (!$this->image || !function_exists('imagewebp')) {
            return false;
        }

        return imagewebp($this->imageResized, $savePath, $imageQuality);
    }

    private function getExifRotateAngle()
    {
        $orientation = isset($this->exif['Orientation']) ? $this->exif['Orientation'] : null;

        switch ($orientation) {
            case 3:
                return 180;
            case 6:
                return 270;
            case 8:
                return 90;
            default:
                return 0;
        }
    }

    public function fixExifOrientation()
    {
        $this->imageResized = imagerotate($this->imageResized, $this->getExifRotateAngle(), 0);
    }
}

?>
