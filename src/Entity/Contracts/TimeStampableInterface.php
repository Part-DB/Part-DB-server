<?php

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Entity\Contracts;

use DateTime;

interface TimeStampableInterface
{
    /**
     * Returns the last time when the element was modified.
     * Returns null if the element was not yet saved to DB yet.
     *
     * @return DateTime|null the time of the last edit
     */
    public function getLastModified(): ?DateTime;

    /**
     * Returns the date/time when the element was created.
     * Returns null if the element was not yet saved to DB yet.
     *
     * @return DateTime|null the creation time of the part
     */
    public function getAddedDate(): ?DateTime;
}
