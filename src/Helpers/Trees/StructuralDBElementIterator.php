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

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Helpers\Trees;

use App\Entity\Base\AbstractStructuralDBElement;
use ArrayIterator;
use Doctrine\Common\Collections\Collection;
use RecursiveIterator;

final class StructuralDBElementIterator extends ArrayIterator implements RecursiveIterator
{
    public function __construct($nodes)
    {
        parent::__construct($nodes);
    }

    public function hasChildren()
    {
        /** @var AbstractStructuralDBElement $element */
        $element = $this->current();

        return ! empty($element->getSubelements());
    }

    public function getChildren()
    {
        /** @var AbstractStructuralDBElement $element */
        $element = $this->current();

        $subelements = $element->getSubelements();
        if (is_array($subelements)) {
            $array = $subelements;
        } elseif ($subelements instanceof Collection) {
            $array = $subelements->toArray();
        } else {
            throw new \InvalidArgumentException('Invalid subelements type on $element!');
        }

        return new self($array);
    }
}
