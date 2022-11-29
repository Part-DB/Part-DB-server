<?php
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

declare(strict_types=1);

namespace App\Helpers\Trees;

use App\Entity\Base\AbstractStructuralDBElement;
use ArrayIterator;
use Doctrine\Common\Collections\Collection;
use InvalidArgumentException;
use RecursiveIterator;

final class StructuralDBElementIterator extends ArrayIterator implements RecursiveIterator
{
    public function __construct($nodes)
    {
        parent::__construct($nodes);
    }

    public function hasChildren(): bool
    {
        /** @var AbstractStructuralDBElement $element */
        $element = $this->current();

        return !empty($element->getSubelements());
    }

    public function getChildren(): StructuralDBElementIterator
    {
        /** @var AbstractStructuralDBElement $element */
        $element = $this->current();

        $subelements = $element->getSubelements();
        if (is_array($subelements)) {
            $array = $subelements;
        } elseif ($subelements instanceof Collection) {
            $array = $subelements->toArray();
        } else {
            throw new InvalidArgumentException('Invalid subelements type on $element!');
        }

        return new self($array);
    }
}
