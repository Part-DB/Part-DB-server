<?php

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
namespace App\Repository;

use App\Entity\Base\AbstractPartsContainingDBElement;
use App\Entity\Base\PartsContainingRepositoryInterface;
use App\Entity\Parts\Part;
use InvalidArgumentException;

abstract class AbstractPartsContainingRepository extends StructuralDBElementRepository implements PartsContainingRepositoryInterface
{
    /** @var int The maximum number of levels for which we can recurse before throwing an error */
    private const RECURSION_LIMIT = 50;

    /**
     * Returns all parts associated with this element.
     *
     * @param  object  $element  the element for which the parts should be determined
     * @param  array  $order_by  The order of the parts. Format ['name' => 'ASC']
     *
     * @return Part[]
     */
    abstract public function getParts(object $element, array $order_by = ['name' => 'ASC']): array;

    /**
     * Gets the count of the parts associated with this element.
     *
     * @param  object  $element  the element for which the parts should be determined
     * @return int
     */
    abstract public function getPartsCount(object $element): int;

    /**
     * Returns the count of the parts associated with this element and all its children.
     * Please be aware that this function is pretty slow on large trees!
     * @return int
     */
    public function getPartsCountRecursive(AbstractPartsContainingDBElement $element): int
    {
        return $this->getPartsCountRecursiveWithDepthN($element, self::RECURSION_LIMIT);
    }

    /**
     * The implementation of the recursive function to get the parts count.
     * This function is used to limit the recursion depth (remaining_depth is decreased on each call).
     * If the recursion limit is reached (remaining_depth <= 0), a RuntimeException is thrown.
     * @internal This function is not intended to be called directly, use getPartsCountRecursive() instead.
     * @return int
     */
    protected function getPartsCountRecursiveWithDepthN(AbstractPartsContainingDBElement $element, int $remaining_depth): int
    {
        if ($remaining_depth <= 0) {
            throw new \RuntimeException('Recursion limit reached!');
        }

        $count = $this->getPartsCount($element);

        //If the element is its own parent, we have a loop in the tree, so we stop here.
        if ($element->getParent() === $element) {
            return 0;
        }

        foreach ($element->getChildren() as $child) {
            $count += $this->getPartsCountRecursiveWithDepthN($child, $remaining_depth - 1);
        }

        return $count;
    }

    protected function getPartsByField(object $element, array $order_by, string $field_name): array
    {
        if (!$element instanceof AbstractPartsContainingDBElement) {
            throw new InvalidArgumentException('$element must be an instance of AbstractPartContainingDBElement!');
        }

        $repo = $this->getEntityManager()->getRepository(Part::class);

        return $repo->findBy([$field_name => $element], $order_by);
    }

    protected function getPartsCountByField(object $element, string $field_name): int
    {
        if (!$element instanceof AbstractPartsContainingDBElement) {
            throw new InvalidArgumentException('$element must be an instance of AbstractPartContainingDBElement!');
        }

        $repo = $this->getEntityManager()->getRepository(Part::class);

        return $repo->count([$field_name => $element]);
    }
}
