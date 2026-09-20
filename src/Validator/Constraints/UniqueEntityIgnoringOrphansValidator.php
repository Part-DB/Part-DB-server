<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\OwningSideMapping;
use ReflectionProperty;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * @see \App\Tests\Validator\Constraints\UniqueEntityIgnoringOrphansValidatorTest
 */
class UniqueEntityIgnoringOrphansValidator extends ConstraintValidator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueEntityIgnoringOrphans) {
            throw new UnexpectedTypeException($constraint, UniqueEntityIgnoringOrphans::class);
        }

        if (null === $value) {
            return;
        }

        if (!\is_object($value)) {
            throw new UnexpectedValueException($value, 'object');
        }

        $class = $this->entityManager->getClassMetadata($value::class);

        $criteria = [];
        foreach ($constraint->fields as $field) {
            if (!$class->hasField($field) && !$class->hasAssociation($field)) {
                throw new ConstraintDefinitionException(\sprintf('The field "%s" is not mapped by Doctrine, so it cannot be validated for uniqueness.', $field));
            }

            $fieldValue = $this->readProperty($value, $field);
            if (null === $fieldValue && $constraint->ignoreNull) {
                return;
            }

            $criteria[$field] = $fieldValue;
        }

        if (!$class->hasAssociation($constraint->ownerField)) {
            throw new ConstraintDefinitionException(\sprintf('The ownerField "%s" configured on %s is not a Doctrine association on "%s".', $constraint->ownerField, UniqueEntityIgnoringOrphans::class, $class->getName()));
        }

        $ownerAssociation = $class->getAssociationMapping($constraint->ownerField);
        $inverseProperty = $ownerAssociation instanceof OwningSideMapping ? $ownerAssociation->inversedBy : null;

        if (null !== $inverseProperty) {
            $ownerClass = $this->entityManager->getClassMetadata($ownerAssociation->targetEntity);
            if (!$ownerClass->hasAssociation($inverseProperty) || !$ownerClass->getAssociationMapping($inverseProperty)->orphanRemoval) {
                throw new ConstraintDefinitionException(\sprintf('The association "%s::$%s" (the inverse side of "%s::$%s") does not have orphanRemoval enabled. %s only makes sense for a collection Doctrine actually schedules a removal for when an entity is taken out of it - use a plain UniqueEntity constraint instead.', $ownerClass->getName(), $inverseProperty, $class->getName(), $constraint->ownerField, UniqueEntityIgnoringOrphans::class));
            }
        }

        $matches = $this->entityManager->getRepository($value::class)->findBy($criteria);

        $conflicts = array_filter(
            $matches,
            fn (object $match): bool => $match !== $value && !$this->isOrphanedFromOwnerCollection($match, $constraint->ownerField, $inverseProperty),
        );

        if (!$conflicts) {
            return;
        }

        $errorPath = $constraint->errorPath ?? $constraint->fields[0];
        $this->context->buildViolation($constraint->message)
            ->atPath($errorPath)
            ->setParameter('{{ value }}', $this->formatValue($criteria[$errorPath] ?? reset($criteria), self::OBJECT_TO_STRING))
            ->setInvalidValue($criteria[$errorPath] ?? null)
            ->setCode(UniqueEntityIgnoringOrphans::NOT_UNIQUE_ERROR)
            ->setCause(array_values($conflicts))
            ->addViolation();
    }

    /**
     * Whether $match has already been removed from its owner's live collection and is only waiting for
     * Doctrine's orphan removal to delete it on the next flush - in which case it must not be treated as
     * a uniqueness conflict.
     */
    private function isOrphanedFromOwnerCollection(object $match, string $ownerField, ?string $inverseProperty): bool
    {
        if (null === $inverseProperty) {
            return false;
        }

        $owner = $this->readProperty($match, $ownerField);
        if (!\is_object($owner)) {
            return false;
        }

        $collection = $this->readProperty($owner, $inverseProperty);
        if (!$collection instanceof Collection) {
            return false;
        }

        return !$collection->contains($match);
    }

    private function readProperty(object $object, string $property): mixed
    {
        return (new ReflectionProperty($object, $property))->getValue($object);
    }
}
