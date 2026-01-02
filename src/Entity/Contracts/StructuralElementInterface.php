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

namespace App\Entity\Contracts;

use Doctrine\Common\Collections\Collection;

/**
 * Interface for structural elements that form a tree hierarchy.
 */
interface StructuralElementInterface
{
    /**
     * Get the parent of this element.
     *
     * @return static|null The parent element. Null if this element does not have a parent.
     */
    public function getParent(): ?self;

    /**
     * Get all sub elements of this element.
     *
     * @return Collection<static>|iterable all subelements
     */
    public function getChildren(): iterable;

    /**
     * Checks if this element is a root element (has no parent).
     *
     * @return bool true if this element is a root element
     */
    public function isRoot(): bool;

    /**
     * Get the full path.
     *
     * @param string $delimiter the delimiter of the returned string
     *
     * @return string the full path (incl. the name of this element), delimited by $delimiter
     */
    public function getFullPath(string $delimiter = ' → '): string;

    /**
     * Get the level.
     *
     * The level of the root node is -1.
     *
     * @return int the level of this element (zero means a most top element)
     */
    public function getLevel(): int;
}
