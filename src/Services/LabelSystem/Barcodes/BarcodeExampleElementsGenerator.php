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

use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\Storelocation;

final class BarcodeExampleElementsGenerator
{
    public function getElement(string $type): object
    {
        switch ($type) {
            case 'part':
                return $this->getExamplePart();
            case 'part_lot':
                return $this->getExamplePartLot();
            case 'storelocation':
                return $this->getStorelocation();
            default:
                throw new \InvalidArgumentException('Unknown $type.');
        }
    }

    public function getExamplePart(): Part
    {
        $part = new Part();
        $part->setName('Example Part');
        $part->setDescription('<b>Part</b> description');
        $part->setComment('<i>Part</i> comment');

        $part->setCategory($this->getStructuralData(Category::class));
        $part->setFootprint($this->getStructuralData(Footprint::class));
        $part->setManufacturer($this->getStructuralData(Manufacturer::class));

        $part->setMass(123.4);
        $part->setManufacturerProductNumber('CUSTOM MPN');
        $part->setTags('Tag1, Tag2, Tag3');
        $part->setManufacturingStatus('active');
        $part->updateTimestamps();

        $part->setFavorite(true);
        $part->setMinAmount(100);
        $part->setNeedsReview(true);

        return $part;
    }

    public function getExamplePartLot(): PartLot
    {
        $lot = new PartLot();
        $lot->setPart($this->getExamplePart());

        $lot->setDescription('Example Lot');
        $lot->setComment('Lot comment');
        $lot->setExpirationDate(new \DateTime('+1 days'));
        $lot->setStorageLocation($this->getStructuralData(Storelocation::class));
        $lot->setAmount(123);

        return $lot;
    }

    private function getStorelocation(): Storelocation
    {
        $storelocation = new Storelocation();
        $storelocation->setName('Location 1');
        $storelocation->setComment('Example comment');
        $storelocation->updateTimestamps();

        $parent = new Storelocation();
        $parent->setName('Parent');

        $storelocation->setParent($parent);

        return $storelocation;
    }

    private function getStructuralData(string $class): AbstractStructuralDBElement
    {
        if (! is_a($class, AbstractStructuralDBElement::class, true)) {
            throw new \InvalidArgumentException('$class must be an child of AbstractStructuralDBElement');
        }

        /** @var AbstractStructuralDBElement $parent */
        $parent = new $class();
        $parent->setName('Example');

        /** @var AbstractStructuralDBElement $child */
        $child = new $class();
        $child->setName((new \ReflectionClass($class))->getShortName());
        $child->setParent($parent);

        return $child;
    }
}
