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

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * This trait provides helper methods for entity mergers.
 * By default, it uses the value from the target entity, unless it not fullfills a condition.
 */
trait EntityMergerHelperTrait
{
    protected PropertyAccessorInterface $property_accessor;

    #[Required]
    public function setPropertyAccessor(PropertyAccessorInterface $property_accessor): void
    {
        $this->property_accessor = $property_accessor;
    }

    /**
     * Choice the value to use from the target or the other entity by using a callback function.
     *
     * @param  callable  $callback The callback to use. The signature is: function($target_value, $other_value, $target, $other, $field). The callback should return the value to use.
     * @param  object  $target The target entity
     * @param  object  $other The other entity
     * @param  string  $field The field to use
     * @return object The target entity with the value set
     */
    protected function useCallback(callable $callback, object $target, object $other, string $field): object
    {
        //Get the values from the entities
        $target_value = $this->property_accessor->getValue($target, $field);
        $other_value = $this->property_accessor->getValue($other, $field);

        //Call the callback, with the signature: function($target_value, $other_value, $target, $other, $field)
        //The callback should return the value to use
        $value = $callback($target_value, $other_value, $target, $other, $field);

        //Set the value
        $this->property_accessor->setValue($target, $field, $value);

        return $target;
    }

    /**
     * Use the value from the other entity, if the value from the target entity is null.
     *
     * @param  object  $target The target entity
     * @param  object  $other The other entity
     * @param  string  $field The field to use
     * @return object The target entity with the value set
     */
    protected function useOtherValueIfNotNull(object $target, object $other, string $field): object
    {
        return $this->useCallback(
            function ($target_value, $other_value) {
                return $target_value ?? $other_value;
            },
            $target,
            $other,
            $field
        );

    }

    /**
     * Use the value from the other entity, if the value from the target entity is empty.
     *
     * @param  object  $target The target entity
     * @param  object  $other The other entity
     * @param  string  $field The field to use
     * @return object The target entity with the value set
     */
    protected function useOtherValueIfNotEmtpy(object $target, object $other, string $field): object
    {
        return $this->useCallback(
            function ($target_value, $other_value) {
                return empty($target_value) ? $other_value : $target_value;
            },
            $target,
            $other,
            $field
        );
    }

    /**
     * Use the larger value from the target and the other entity for the given field.
     *
     * @param  object  $target
     * @param  object  $other
     * @param  string  $field
     * @return object
     */
    protected function useLargerValue(object $target, object $other, string $field): object
    {
        return $this->useCallback(
            function ($target_value, $other_value) {
                return max($target_value, $other_value);
            },
            $target,
            $other,
            $field
        );
    }

    /**
     * Use the smaller value from the target and the other entity for the given field.
     *
     * @param  object  $target
     * @param  object  $other
     * @param  string  $field
     * @return object
     */
    protected function useSmallerValue(object $target, object $other, string $field): object
    {
        return $this->useCallback(
            function ($target_value, $other_value) {
                return min($target_value, $other_value);
            },
            $target,
            $other,
            $field
        );
    }

    /**
     * Merge the collections from the target and the other entity for the given field and put all items into the target collection.
     * @param  object  $target
     * @param  object  $other
     * @param  string  $field
     * @return object
     */
    protected function mergeCollections(object $target, object $other, string $field): object
    {
        $target_collection = $this->property_accessor->getValue($target, $field);
        $other_collection = $this->property_accessor->getValue($other, $field);

        if (!$target_collection instanceof Collection) {
            throw new \InvalidArgumentException("The target field $field is not a collection");
        }

        //Clone the items from the other collection
        $clones = [];
        foreach ($other_collection as $item) {
            $clones[] = clone $item;
        }

        $tmp = array_merge($target_collection->toArray(), $clones);

        //Create a new collection with the clones and merge it into the target collection
        $this->property_accessor->setValue($target, $field,  $tmp);

        return $target;
    }
}