<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace App\Entity\LabelSystem;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Embeddable()
 */
class LabelOptions
{
    public const BARCODE_TYPES = ['none', /*'ean8',*/ 'qr', 'code39', 'datamatrix', 'code93', 'code128'];
    public const SUPPORTED_ELEMENTS = ['part', 'part_lot'];
    public const PICTURE_TYPES = ['none', 'element_picture', 'main_attachment'];

    public const LINES_MODES = ['html', 'twig'];

    /**
     * @var float The page size of the label in mm
     * @Assert\Positive()
     * @ORM\Column(type="float")
     */
    protected $width = 50.0;

    /**
     * @var float The page size of the label in mm
     * @Assert\Positive()
     * @ORM\Column(type="float")
     */
    protected $height = 30.0;

    /**
     * @var string The type of the barcode that should be used in the label (e.g. 'qr')
     * @Assert\Choice(choices=LabelOptions::BARCODE_TYPES)
     * @ORM\Column(type="string")
     */
    protected $barcode_type = 'none';

    /**
     * @var string What image should be shown along the
     * @Assert\Choice(choices=LabelOptions::PICTURE_TYPES)
     * @ORM\Column(type="string")
     */
    protected $picture_type = 'none';

    /**
     * @var string
     * @Assert\Choice(choices=LabelOptions::SUPPORTED_ELEMENTS)
     * @ORM\Column(type="string")
     */
    protected $supported_element = 'part';

    /**
     * @var string Any additional CSS for the label.
     * @ORM\Column(type="text")
     */
    protected $additional_css = '';

    /** @var string The mode that will be used to interpret the lines.
     * @Assert\Choice(choices=LabelOptions::LINES_MODES)
     * @ORM\Column(type="string")
     */
    protected $lines_mode = 'html';

    /**
     * @var string
     * @ORM\Column(type="text")
     */
    protected $lines = '';

    /**
     * @return float
     */
    public function getWidth(): float
    {
        return $this->width;
    }

    /**
     * @param  float  $width
     * @return LabelOptions
     */
    public function setWidth(float $width): LabelOptions
    {
        $this->width = $width;
        return $this;
    }

    /**
     * @return float
     */
    public function getHeight(): float
    {
        return $this->height;
    }

    /**
     * @param  float  $height
     * @return LabelOptions
     */
    public function setHeight(float $height): LabelOptions
    {
        $this->height = $height;
        return $this;
    }

    /**
     * @return string
     */
    public function getBarcodeType(): string
    {
        return $this->barcode_type;
    }

    /**
     * @param  string  $barcode_type
     * @return LabelOptions
     */
    public function setBarcodeType(string $barcode_type): LabelOptions
    {
        $this->barcode_type = $barcode_type;
        return $this;
    }

    /**
     * @return string
     */
    public function getPictureType(): string
    {
        return $this->picture_type;
    }

    /**
     * @param  string  $picture_type
     * @return LabelOptions
     */
    public function setPictureType(string $picture_type): LabelOptions
    {
        $this->picture_type = $picture_type;
        return $this;
    }

    /**
     * @return string
     */
    public function getSupportedElement(): string
    {
        return $this->supported_element;
    }

    /**
     * @param  string  $supported_element
     * @return LabelOptions
     */
    public function setSupportedElement(string $supported_element): LabelOptions
    {
        $this->supported_element = $supported_element;
        return $this;
    }

    /**
     * @return string
     */
    public function getLines(): string
    {
        return $this->lines;
    }

    /**
     * @param  string  $lines
     * @return LabelOptions
     */
    public function setLines(string $lines): LabelOptions
    {
        $this->lines = $lines;
        return $this;
    }

    /**
     * Gets additional CSS (it will simply be attached 
     * @return string
     */
    public function getAdditionalCss(): string
    {
        return $this->additional_css;
    }

    /**
     *
     * @param  string  $additional_css
     * @return LabelOptions
     */
    public function setAdditionalCss(string $additional_css): LabelOptions
    {
        $this->additional_css = $additional_css;
        return $this;
    }

    /**
     * @return string
     */
    public function getLinesMode(): string
    {
        return $this->lines_mode;
    }

    /**
     * @param  string  $lines_mode
     * @return LabelOptions
     */
    public function setLinesMode(string $lines_mode): LabelOptions
    {
        $this->lines_mode = $lines_mode;
        return $this;
    }





}