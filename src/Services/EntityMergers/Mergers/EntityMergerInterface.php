<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Services\EntityMergers\Mergers;


/**
 * @template T of object
 */
interface EntityMergerInterface
{
    /**
     * Determines if this merger supports merging the other entity into the target entity.
     * @param  object  $target
     * @phpstan-param T $target
     * @param  object  $other
     * @phpstan-param T $other
     * @param  array  $context
     * @return bool True if this merger supports merging the other entity into the target entity, false otherwise
     */
    public function supports(object $target, object $other, array $context = []): bool;

    /**
     * Merge the other entity into the target entity.
     * The target entity will be modified and returned.
     * @param  object  $target
     * @phpstan-param T $target
     * @param  object  $other
     * @phpstan-param T $other
     * @param  array  $context
     * @phpstan-return T
     * @return object
     */
    public function merge(object $target, object $other, array $context = []): object;
}