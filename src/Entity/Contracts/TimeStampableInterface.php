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

interface TimeStampableInterface
{
    /**
     * Returns the last time when the element was modified.
     * Returns null if the element was not yet saved to DB yet.
     *
     * @return \DateTimeInterface|null the time of the last edit
     */
    public function getLastModified(): ?\DateTimeInterface;

    /**
     * Returns the date/time when the element was created.
     * Returns null if the element was not yet saved to DB yet.
     *
     * @return \DateTimeInterface|null the creation time of the part
     */
    public function getAddedDate(): ?\DateTimeInterface;
}
