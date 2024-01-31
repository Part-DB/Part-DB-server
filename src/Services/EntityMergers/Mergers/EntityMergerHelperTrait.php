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

use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\AttachmentContainingDBElement;
use App\Entity\Base\AbstractNamedDBElement;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Parameters\AbstractParameter;
use App\Entity\Parts\Part;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Contracts\Service\Attribute\Required;

use function Symfony\Component\String\u;

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
     * Perform an OR operation on the boolean values from the target and the other entity for the given field.
     * This effectively means that the value is true, if it is true in at least one of the entities.
     * @param  object  $target
     * @param  object  $other
     * @param  string  $field
     * @return object
     */
    protected function useTrueValue(object $target, object $other, string $field): object
    {
        return $this->useCallback(
            function (bool $target_value, bool $other_value): bool {
                return $target_value || $other_value;
            },
            $target,
            $other,
            $field
        );
    }

    /**
     * Perform a merge of comma separated lists from the target and the other entity for the given field.
     * The values are merged and duplicates are removed.
     * @param  object  $target
     * @param  object  $other
     * @param  string  $field
     * @return object
     */
    protected function mergeTags(object $target, object $other, string $field, string $separator = ','): object
    {
        return $this->useCallback(
            function (string|null $t, string|null $o) use ($separator): string {
                //Explode the strings into arrays
                $t_array = explode($separator, $t ?? '');
                $o_array = explode($separator, $o ?? '');

                //Merge the arrays and remove duplicates
                $tmp = array_unique(array_merge($t_array, $o_array));

                //Implode the array back to a string
                return implode($separator, $tmp);
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
     * @param  callable|null  $equal_fn A function, which checks if two items are equal. The signature is: function(object $target, object other): bool.
     * Return true if the items are equal, false otherwise. If two items are equal, the item from the other collection is not added to the target collection.
     * If null, the items are compared by (instance) identity.
     * @return object
     */
    protected function mergeCollections(object $target, object $other, string $field, ?callable $equal_fn = null): object
    {
        $target_collection = $this->property_accessor->getValue($target, $field);
        $other_collection = $this->property_accessor->getValue($other, $field);

        if (!$target_collection instanceof Collection) {
            throw new \InvalidArgumentException("The target field $field is not a collection");
        }

        //Clone the items from the other collection
        $clones = [];
        foreach ($other_collection as $item) {
            //Check if the item is already in the target collection
            if ($equal_fn !== null) {
                foreach ($target_collection as $target_item) {
                    if ($equal_fn($target_item, $item)) {
                        continue 2;
                    }
                }
            } else {
                if ($target_collection->contains($item)) {
                    continue;
                }
            }

            $clones[] = clone $item;
        }

        $tmp = array_merge($target_collection->toArray(), $clones);

        //Create a new collection with the clones and merge it into the target collection
        $this->property_accessor->setValue($target, $field,  $tmp);

        return $target;
    }

    /**
     * Merge the attachments from the target and the other entity.
     * @param  AttachmentContainingDBElement  $target
     * @param  AttachmentContainingDBElement  $other
     * @return object
     */
    protected function mergeAttachments(AttachmentContainingDBElement $target, AttachmentContainingDBElement $other): object
    {
        return $this->mergeCollections($target, $other, 'attachments', function (Attachment $t, Attachment $o): bool {
            return $t->getName() === $o->getName()
                && $t->getAttachmentType() === $o->getAttachmentType()
                && $t->getPath() === $o->getPath();
        });
    }

    /**
     * Merge the parameters from the target and the other entity.
     * @param  AbstractStructuralDBElement|Part  $target
     * @param  AbstractStructuralDBElement|Part  $other
     * @return object
     */
    protected function mergeParameters(AbstractStructuralDBElement|Part $target, AbstractStructuralDBElement|Part $other): object
    {
        return $this->mergeCollections($target, $other, 'parameters', function (AbstractParameter $t, AbstractParameter $o): bool {
            return $t->getName() === $o->getName()
                && $t->getSymbol() === $o->getSymbol()
                && $t->getUnit() === $o->getUnit()
                && $t->getValueMax() === $o->getValueMax()
                && $t->getValueMin() === $o->getValueMin()
                && $t->getValueTypical() === $o->getValueTypical()
                && $t->getValueText() === $o->getValueText()
                && $t->getGroup() === $o->getGroup();
        });
    }

    /**
     * Check if the two strings have equal content.
     * This method is case-insensitive and ignores whitespace.
     * @param  string|\Stringable  $t
     * @param  string|\Stringable  $o
     * @return bool
     */
    protected function areStringsEqual(string|\Stringable $t, string|\Stringable $o): bool
    {
        $t_str = u($t)->trim()->folded();
        $o_str = u($o)->trim()->folded();

        return $t_str->equalsTo($o_str);
    }

    /**
     * Merge the text from the target and the other entity for the given field by attaching the other text to the target text via the given separator.
     * For example, if the target text is "Hello" and the other text is "World", the result is "Hello / World".
     * If the text is the same in both entities, the target text is returned.
     * @param  object  $target
     * @param  object  $other
     * @param  string  $field
     * @param  string  $separator
     * @return object
     */
    protected function mergeTextWithSeparator(object $target, object $other, string $field, string $separator = ' / '): object
    {
        return $this->useCallback(
            function (string $t, string $o) use ($separator): string {
                //Check if the strings are equal
                if ($this->areStringsEqual($t, $o)) {
                    return $t;
                }

                //Skip empty strings
                if (trim($t) === '') {
                    return trim($o);
                }
                if (trim($o) === '') {
                    return trim($t);
                }

                return trim($t) . $separator . trim($o);
            },
            $target,
            $other,
            $field
        );
    }

    /**
     * Merge two comments from the target and the other entity for the given field.
     * The comments of the both entities get concated, while the second part get a headline with the name of the old part.
     * @param  AbstractNamedDBElement  $target
     * @param  AbstractNamedDBElement  $other
     * @param  string  $field
     * @return object
     */
    protected function mergeComment(AbstractNamedDBElement $target, AbstractNamedDBElement $other, string $field = 'comment'): object
    {
        return $this->useCallback(
            function (string $t, string $o) use ($other): string {
                //Check if the strings are equal
                if ($this->areStringsEqual($t, $o)) {
                    return $t;
                }

                //Skip empty strings
                if (trim($t) === '') {
                    return trim($o);
                }
                if (trim($o) === '') {
                    return trim($t);
                }

                return sprintf("%s\n\n<b>%s:</b>\n%s",
                    trim($t),
                    $other->getName(),
                    trim($o)
                );
            },
            $target,
            $other,
            $field
        );
    }
}