<?php

declare(strict_types=1);

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

namespace App\Services\LabelSystem\Barcodes;

use App\Entity\Base\AbstractDBElement;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\Storelocation;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class BarcodeContentGenerator
{
    public const PREFIX_MAP = [
        Part::class => 'P',
        PartLot::class => 'L',
        Storelocation::class => 'S',
    ];

    private const URL_MAP = [
        Part::class => 'part',
        PartLot::class => 'lot',
        Storelocation::class => 'location',
    ];
    private $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * Generates a fixed URL to the given Element that can be embedded in a 2D code (e.g. QR code).
     */
    public function getURLContent(AbstractDBElement $target): string
    {
        $type = $this->classToString(self::URL_MAP, $target);

        return  $this->urlGenerator->generate('scan_qr', [
            'type' => $type,
            'id' => $target->getID() ?? 0,
            '_locale' => null,
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * Returns a Code that can be used in a 1D barcode.
     * The return value has a format of "L0123".
     */
    public function get1DBarcodeContent(AbstractDBElement $target): string
    {
        $prefix = $this->classToString(self::PREFIX_MAP, $target);
        $id = sprintf('%04d', $target->getID() ?? 0);

        return $prefix.$id;
    }

    private function classToString(array $map, object $target): string
    {
        $class = get_class($target);
        if (isset($map[$class])) {
            return $map[$class];
        }

        foreach ($map as $class => $string) {
            if (is_a($target, $class)) {
                return $string;
            }
        }

        throw new \InvalidArgumentException('Unknown object class '.get_class($target));
    }
}
