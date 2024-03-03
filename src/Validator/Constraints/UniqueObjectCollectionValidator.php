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

namespace App\Validator\Constraints;

use App\Entity\Base\AbstractDBElement;
use App\Validator\UniqueValidatableInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class UniqueObjectCollectionValidator extends ConstraintValidator
{

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueObjectCollection) {
            throw new UnexpectedTypeException($constraint, UniqueObjectCollection::class);
        }

        $fields = (array) $constraint->fields;

        if (null === $value) {
            return;
        }

        if (!\is_array($value) && !$value instanceof \IteratorAggregate) {
            throw new UnexpectedValueException($value, 'array|IteratorAggregate');
        }

        $collectionElements = [];
        $normalizer = $this->getNormalizer($constraint);
        foreach ($value as $key => $object) {

            if (!$object instanceof UniqueValidatableInterface) {
                throw new UnexpectedValueException($object, UniqueValidatableInterface::class);
            }

            //Convert the object to an array using the helper function
            $element = $object->getComparableFields();

            if ($fields && !$element = $this->reduceElementKeys($fields, $element, $constraint)) {
                continue;
            }

            $element = $normalizer($element);

            if (\in_array($element, $collectionElements, true)) {

                $violation = $this->context->buildViolation($constraint->message);

                //Use the first supplied field as the target field, or the first defined field name of the element if none is supplied
                $target_field = $constraint->fields[0] ?? array_keys($element)[0];

                $violation->atPath('[' . $key . ']' . '.' . $target_field);

                $violation->setParameter('{{ object }}', $this->formatValue($object, ConstraintValidator::OBJECT_TO_STRING))
                    ->setCode(UniqueObjectCollection::IS_NOT_UNIQUE)
                    ->addViolation();

                return;
            }
            $collectionElements[] = $element;
        }
    }

    private function getNormalizer(UniqueObjectCollection $unique): callable
    {
        return $unique->normalizer ?? static fn($value) => $value;
    }

    private function reduceElementKeys(array $fields, array $element, UniqueObjectCollection $constraint): array
    {
        $output = [];
        foreach ($fields as $field) {
            if (!\is_string($field)) {
                throw new UnexpectedTypeException($field, 'string');
            }
            if (\array_key_exists($field, $element)) {
                //Ignore null values if specified
                if ($element[$field] === null && $constraint->allowNull) {
                    continue;
                }

                $output[$field] = $element[$field];
            }
        }

        return $output;
    }

}