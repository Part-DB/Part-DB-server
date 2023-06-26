<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Embeddable]
class LabelOptions
{
    /**
     * @var float The page size of the label in mm
     */
    #[Assert\Positive]
    #[ORM\Column(type: Types::FLOAT)]
    protected float $width = 50.0;

    /**
     * @var float The page size of the label in mm
     */
    #[Assert\Positive]
    #[ORM\Column(type: Types::FLOAT)]
    protected float $height = 30.0;

    /**
     * @var BarcodeType The type of the barcode that should be used in the label (e.g. 'qr')
     */
    #[ORM\Column(type: Types::STRING, enumType: BarcodeType::class)]
    protected BarcodeType $barcode_type = BarcodeType::NONE;

    /**
     * @var LabelPictureType What image should be shown along the label
     */
    #[ORM\Column(type: Types::STRING, enumType: LabelPictureType::class)]
    protected LabelPictureType $picture_type = LabelPictureType::NONE;

    #[ORM\Column(type: Types::STRING, enumType: LabelSupportedElement::class)]
    protected LabelSupportedElement $supported_element = LabelSupportedElement::PART;

    /**
     * @var string any additional CSS for the label
     */
    #[ORM\Column(type: Types::TEXT)]
    protected string $additional_css = '';

    /** @var LabelProcessMode The mode that will be used to interpret the lines
     */
    #[ORM\Column(type: Types::STRING, enumType: LabelProcessMode::class, name: 'lines_mode')]
    protected LabelProcessMode $process_mode = LabelProcessMode::PLACEHOLDER;

    /**
     * @var string
     */
    #[ORM\Column(type: Types::TEXT)]
    protected string $lines = '';

    public function getWidth(): float
    {
        return $this->width;
    }

    public function setWidth(float $width): self
    {
        $this->width = $width;

        return $this;
    }

    public function getHeight(): float
    {
        return $this->height;
    }

    public function setHeight(float $height): self
    {
        $this->height = $height;

        return $this;
    }

    public function getBarcodeType(): BarcodeType
    {
        return $this->barcode_type;
    }

    public function setBarcodeType(BarcodeType $barcode_type): self
    {
        $this->barcode_type = $barcode_type;

        return $this;
    }

    public function getPictureType(): LabelPictureType
    {
        return $this->picture_type;
    }

    public function setPictureType(LabelPictureType $picture_type): self
    {
        $this->picture_type = $picture_type;

        return $this;
    }

    public function getSupportedElement(): LabelSupportedElement
    {
        return $this->supported_element;
    }

    public function setSupportedElement(LabelSupportedElement $supported_element): self
    {
        $this->supported_element = $supported_element;

        return $this;
    }

    public function getLines(): string
    {
        return $this->lines;
    }

    public function setLines(string $lines): self
    {
        $this->lines = $lines;

        return $this;
    }

    /**
     * Gets additional CSS (it will simply be attended to base CSS).
     */
    public function getAdditionalCss(): string
    {
        return $this->additional_css;
    }

    public function setAdditionalCss(string $additional_css): self
    {
        $this->additional_css = $additional_css;

        return $this;
    }

    public function getProcessMode(): LabelProcessMode
    {
        return $this->process_mode;
    }

    public function setProcessMode(LabelProcessMode $process_mode): self
    {
        $this->process_mode = $process_mode;

        return $this;
    }
}
