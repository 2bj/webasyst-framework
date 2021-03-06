<?php

/*
 * This file is part of Webasyst framework.
 *
 * Licensed under the terms of the GNU Lesser General Public License (LGPL).
 * http://www.webasyst.com/framework/license/
 *
 * @link http://www.webasyst.com/
 * @author Webasyst LLC
 * @copyright 2011 Webasyst LLC
 * @package wa-system
 * @subpackage image
 */
class waImageImagick extends waImage
{
    /**
     * @var Imagick
     */
    protected $im;

    /**
     * Checks if ImageMagick is enabled.
     * @throws waException
     * @return bool
     */
    public static function check()
    {
        if (!extension_loaded('imagick'))
        {
            throw new waException(_ws('ImageMagick is not installed.'));
        }

        return self::$checked = true;
    }

    public function __construct($file)
    {
        if (!self::$checked) {
            self::check();
        }
        parent::__construct($file);

        $this->im = new Imagick;
        $this->im->readImage($file);
    }

    public function __destruct()
    {
        $this->im->clear();
        $this->im->destroy();
    }


    protected function _resize($width, $height)
    {
        if ($this->im->getNumberImages() > 1) {
            $this->im = $this->im->coalesceImages();
            foreach ($this->im as $animation) {
//                $animation->setImagePage( $animation->getImageWidth(), $animation->getImageHeight(), 0, 0 );
                $animation->resizeImage($width, $height, Imagick::FILTER_CUBIC, 0.5);
            }
        }
        else {
            if ($this->im->resizeImage($width, $height, Imagick::FILTER_CUBIC, 0.5))
            {
                $this->width = $this->im->getImageWidth();
                $this->height = $this->im->getImageHeight();
            }
        }
    }

    protected function _crop($width, $height, $offset_x, $offset_y)
    {
        if ($this->im->cropImage($width, $height, $offset_x, $offset_y))
        {
            $this->width = $this->im->getImageWidth();
            $this->height = $this->im->getImageHeight();

            return true;
        }

        return false;
    }

    protected function _rotate($degrees)
    {
        if ($this->im->rotateImage(new ImagickPixel, $degrees))
        {
            $this->width = $this->im->getImageWidth();
            $this->height = $this->im->getImageHeight();

            return true;
        }

        return false;
    }

    protected function _sharpen($amount)
    {
        //IM not support $amount under 5 (0.15)
        $amount = ($amount < 5) ? 5 : $amount;

        // Amount should be in the range of 0.0 to 3.0
        $amount = ($amount * 3.0) / 100;

        if ($this->im->sharpenImage(0, $amount))
        {
            $this->width = $this->im->getImageWidth();
            $this->height = $this->im->getImageHeight();

            return true;
        }

        return false;
    }

    protected function _save($file, $quality)
    {
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        $type = $this->_save_function($extension, $quality);
        $this->im->setImageCompressionQuality($quality);

        if ($this->im->getNumberImages() > 1 && $extension == "gif") {
            $res = $this->im->writeImages($file, true);
        }
        else {
            $res = $this->im->writeImage($file);
        }
        if ($res) {
            $this->type = $type;
            $this->mime = image_type_to_mime_type($type);
            return true;
        }
        return false;
    }

    protected function _render($type, $quality)
    {
        $type = $this->_save_function($type, $quality);
        $this->im->setImageCompressionQuality($quality);
        $this->type = $type;
        $this->mime = image_type_to_mime_type($type);

        if ($this->im->getNumberImages() > 1 && $type == "gif") {
            return $this->im->getImagesBlob();
        }
        return $this->im->getImageBlob();
    }

    protected function _save_function($extension, & $quality)
    {
        switch (strtolower($extension))
        {
            case 'jpg':
            case 'jpeg':
                $type = IMAGETYPE_JPEG;
                if ($this->type == "png" || $this->type == "gif") {
                    $this->im->borderImage(new ImagickPixel("white"), 1, 1);
                }
                $this->im->setImageFormat('jpeg');
            break;
            case 'gif':
                $type = IMAGETYPE_GIF;
                $this->im->setImageFormat('gif');
            break;
            case 'png':
                $type = IMAGETYPE_PNG;
                $this->im->setImageFormat('png');
            break;
            default:
                throw new waException(_ws(sprintf('Installed ImageMagick does not support %s images', $extension)));
            break;
        }

        $quality = $quality - 5;

        return $type;
    }

    protected function _filter($type)
    {
        switch ($type) {
            case self::FILTER_GRAYSCALE:
                $this->im->setImageColorSpace(Imagick::COLORSPACE_GRAY);
                break;
            case self::FILTER_SEPIA:
                $this->im->sepiaToneImage(80);
                break;
            default:
                $this->im->setImageColorSpace(Imagick::COLORSPACE_GRAY);
                break;
        }
    }

    /**
     * @param array $options
     *     'watermark' => waImage|string $watermark
     *     'opacity' => float|int 0..1
     *     'align' => self::ALIGN_* const
     *     'font_file' => null|string If null - will be used some default font. Note: use when watermark option is text
     *     'font_size' => float Size of font. Note: use when watermark option is text
     *     'font_color' => string Hex-formatted of color (without #). Note: use when watermark option is text
     *     'text_orientation' => self::ORIENTATION_* const. Note: use when watermark option is text
     * @return mixed
     */
    protected function _watermark($options)
    {
        // export options to php-vars
        foreach ($options as $name => $value) {
            $$name = $value;
        }

        $opacity = min(max($opacity, 0), 1);
        /**
         * @var waImage $watermark
         */
        if ($watermark instanceof waImage) {
            $offset = $this->calcWatermarkOffset($watermark->width, $watermark->height, $align);
            $watermark = new Imagick($watermark->file);
            $watermark->evaluateImage(Imagick::EVALUATE_MULTIPLY, $opacity, Imagick::CHANNEL_ALPHA);
            $this->im->compositeImage(
                    $watermark,
                    Imagick::COMPOSITE_DEFAULT,
                    $offset[0],
                    $offset[1]);

            $watermark->clear();
            $watermark->destroy();
        } else {
            $text = (string) $watermark;
            if (!$text) {
                return;
            }
            $font_size = 24*$font_size/18;    // 24px = 18pt
            $font_color = new ImagickPixel('#'.$font_color);

            $watermark = new ImagickDraw();
            $watermark->setFillColor($font_color);
            $watermark->setFillOpacity($opacity);
            if ($font_file && file_exists($font_file)) {
                $watermark->setFont($font_file);
            }
            $watermark->setFontSize($font_size);

            // Throws ImagickException on error
            $metrics = $this->im->queryFontMetrics($watermark, $text);
            $width = $metrics['textWidth'];
            $height = $metrics['textHeight'];
            if ($text_orientation == self::ORIENTATION_VERTICAL) {
                list ($width, $height) = array($height, $width);
            }
            $offset = $this->calcWatermarkOffset($width, $height, $align);
            $offset = $this->watermarkOffsetFix($offset, $width, $height, $text_orientation);
            $this->im->annotateImage($watermark, $offset[0], $offset[1], $text_orientation == self::ORIENTATION_VERTICAL ? -90: 0, $text);
            $watermark->clear();
            $watermark->destroy();
        }
    }

    private function calcWatermarkOffset($width, $height, $align)
    {
        $offset = '';
        $margin = 10;
        switch ($align) {
            case self::ALIGN_TOP_LEFT:
                $offset = array($margin, $margin);
                break;
            case self::ALIGN_TOP_RIGHT:
                $offset = array($this->width - $width - $margin, $margin);
                break;
            case self::ALIGN_BOTTOM_LEFT:
                $offset = array($margin, $this->height - $height - $margin);
                break;
            case self::ALIGN_BOTTOM_RIGHT:
                $offset = array($this->width - $width - $margin, $this->height - $height - $margin);
                break;
        }
        return $offset;
    }

    private function watermarkOffsetFix($offset, $width, $height, $orientation)
    {
        if ($orientation == self::ORIENTATION_HORIZONTAL) {
            $offset[1] += $height;
        } else {
            $offset[0] += $width;
            $offset[1] += $height;
        }
        return $offset;
    }
}
