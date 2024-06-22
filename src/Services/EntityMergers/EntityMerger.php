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


namespace App\Services\EntityMergers;

use App\Services\EntityMergers\Mergers\EntityMergerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

/**
 * This service is used to merge two entities together.
 * It automatically finds the correct merger (implementing EntityMergerInterface) for the two entities if one exists.
 */
class EntityMerger
{
    public function __construct(#[TaggedIterator('app.entity_merger')] protected iterable $mergers)
    {
    }

    /**
     * This function finds the first merger that supports merging the other entity into the target entity.
     * @param  object  $target
     * @param  object  $other
     * @param  array  $context
     * @return EntityMergerInterface|null
     */
    public function findMergerForObject(object $target, object $other, array $context = []): ?EntityMergerInterface
    {
        foreach ($this->mergers as $merger) {
            if ($merger->supports($target, $other, $context)) {
                return $merger;
            }
        }
        return null;
    }

    /**
     * This function merges the other entity into the target entity. If no merger is found an exception is thrown.
     * The target entity will be modified and returned.
     * @param  object  $target
     * @param  object  $other
     * @param  array  $context
     * @template T of object
     * @phpstan-param T $target
     * @phpstan-param T $other
     * @phpstan-return T
     * @return object
     */
    public function merge(object $target, object $other, array $context = []): object
    {
        $merger = $this->findMergerForObject($target, $other, $context);
        if ($merger === null) {
            throw new \RuntimeException('No merger found for merging '.$other::class.' into '.$target::class);
        }
        return $merger->merge($target, $other, $context);
    }
}