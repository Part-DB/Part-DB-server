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

namespace App\Services\LabelSystem;

use App\Entity\LabelSystem\LabelOptions;
use InvalidArgumentException;
use Jbtronics\DompdfFontLoaderBundle\Services\DompdfFactoryInterface;

/**
 * @see \App\Tests\Services\LabelSystem\LabelGeneratorTest
 */
final class LabelGenerator
{
    public const MM_TO_POINTS_FACTOR = 2.83465;

    public function __construct(private readonly LabelHTMLGenerator $labelHTMLGenerator,
        private readonly DompdfFactoryInterface $dompdfFactory)
    {
    }

    /**
     * @param  object|object[]  $elements  An element or an array of elements for which labels should be generated
     */
    public function generateLabel(LabelOptions $options, object|array $elements): string
    {
        if (!is_array($elements)) {
            $elements = [$elements];
        }

        foreach ($elements as $element) {
            if (!$this->supports($options, $element)) {
                throw new InvalidArgumentException('The given options are not compatible with the given element!');
            }
        }

        $dompdf = $this->dompdfFactory->create();
        $dompdf->setPaper($this->mmToPointsArray($options->getWidth(), $options->getHeight()));
        $dompdf->loadHtml($this->labelHTMLGenerator->getLabelHTML($options, $elements));
        $dompdf->render();

        return $dompdf->output() ?? throw new \RuntimeException('Could not generate label!');
    }

    /**
     * Check if the given LabelOptions can be used with $element.
     */
    public function supports(LabelOptions $options, object $element): bool
    {
        $supported_type = $options->getSupportedElement();

        return is_a($element, $supported_type->getEntityClass());
    }

    /**
     * Converts width and height given in mm to a size array, that can be used by DOMPDF for page size.
     *
     * @param float $width  The width of the paper
     * @param float $height The height of the paper
     *
     * @return float[]
     */
    public function mmToPointsArray(float $width, float $height): array
    {
        return [0.0, 0.0, $width * self::MM_TO_POINTS_FACTOR, $height * self::MM_TO_POINTS_FACTOR];
    }
}
