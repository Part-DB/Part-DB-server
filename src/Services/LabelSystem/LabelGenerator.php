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

namespace App\Services\LabelSystem;


use App\Entity\Contracts\NamedElementInterface;
use App\Entity\LabelSystem\LabelOptions;
use App\Entity\Parts\Part;
use App\Services\ElementTypeNameGenerator;
use Dompdf\Dompdf;
use Twig\Environment;

class LabelGenerator
{
    protected const CLASS_SUPPORT_MAPPING = [
        'part' => Part::class,
    ];

    public const MM_TO_POINTS_FACTOR = 2.83465;

    protected $labelHTMLGenerator;

    public function __construct(LabelHTMLGenerator $labelHTMLGenerator)
    {
        $this->labelHTMLGenerator = $labelHTMLGenerator;
    }

    public function generateLabel(LabelOptions $options, object $element): string
    {
        if (!$this->supports($options, $element)) {
            throw new \InvalidArgumentException('The given options are not compatible with the given element!');
        }

        $dompdf = new Dompdf();
        $dompdf->loadHtml($this->labelHTMLGenerator->getLabelHTML($options, $element));

        $dompdf->setPaper($this->mmToPointsArray($options->getWidth(), $options->getHeight()));

        $dompdf->render();
        return $dompdf->output();
    }

    /**
     * Check if the given LabelOptions can be used with $element.
     * @param  LabelOptions  $options
     * @param  object  $element
     * @return bool
     */
    public function supports(LabelOptions $options, object $element)
    {
        $supported_type = $options->getSupportedElement();
        if (!isset(static::CLASS_SUPPORT_MAPPING[$supported_type])) {
            throw new \InvalidArgumentException('Supported type name of the Label options not known!');
        }

        return is_a($element, static::CLASS_SUPPORT_MAPPING[$supported_type]);
    }

    public function mmToPointsArray(float $width, float $height): array
    {
        return [0, 0, $width * self::MM_TO_POINTS_FACTOR, $height * self::MM_TO_POINTS_FACTOR];
    }
}