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

namespace App\Services\ImportExportSystem\PartKeeprImporter;

use App\Entity\Base\AbstractDBElement;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Contracts\TimeStampableInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * This trait contains helper functions for the PartKeeprImporter.
 */
trait PKImportHelperTrait
{
    protected EntityManagerInterface $em;
    protected PropertyAccessorInterface $propertyAccessor;

    /**
     * Assigns the parent to the given entity, using the numerical IDs from the imported data.
     * @param  string  $class
     * @param int|string $element_id
     * @param int|string $parent_id
     * @return AbstractStructuralDBElement The structural element that was modified (with $element_id)
     */
    protected function setParent(string $class, $element_id, $parent_id): AbstractStructuralDBElement
    {
        $element = $this->em->find($class, (int) $element_id);
        if (!$element) {
            throw new \RuntimeException(sprintf('Could not find element with ID %s', $element_id));
        }

        //If the parent is null, we're done
        if (!$parent_id) {
            return $element;
        }

        $parent = $this->em->find($class, (int) $parent_id);
        if (!$parent) {
            throw new \RuntimeException(sprintf('Could not find parent with ID %s', $parent_id));
        }

        $element->setParent($parent);
        return $element;
    }

    /**
     * Sets the given field of the given entity to the entity with the given ID.
     * @return AbstractDBElement
     */
    protected function setAssociationField(AbstractDBElement $element, string $field, string $other_class, $other_id): AbstractDBElement
    {
        //If the parent is null, set the field to null and we're done
        if (!$other_id) {
            $this->propertyAccessor->setValue($element, $field, null);
            return $element;
        }

        $parent = $this->em->find($other_class, (int) $other_id);
        if (!$parent) {
            throw new \RuntimeException(sprintf('Could not find other_class with ID %s', $other_id));
        }

        $this->propertyAccessor->setValue($element, $field, $parent);
        return $element;
    }

    /**
     * Set the ID of an entity to a specific value. Must be called before persisting the entity, but before flushing.
     * @param  AbstractDBElement  $element
     * @param  int|string $id
     * @return void
     */
    protected function setIDOfEntity(AbstractDBElement $element, $id): void
    {
        if (!is_int($id) && !is_string($id)) {
            throw new \InvalidArgumentException('ID must be an integer or string');
        }

        $id = (int) $id;

        $metadata = $this->em->getClassMetadata(get_class($element));
        $metadata->setIdGeneratorType(ClassMetadataInfo::GENERATOR_TYPE_NONE);
        $metadata->setIdGenerator(new \Doctrine\ORM\Id\AssignedGenerator());
        $metadata->setIdentifierValues($element, ['id' => $id]);
    }

    /**
     * Sets the creation date of an entity to a specific value.
     * @return void
     * @throws \Exception
     */
    protected function setCreationDate(TimeStampableInterface $entity, ?string $datetime_str)
    {
        if ($datetime_str) {
            $date = new \DateTime($datetime_str);
        } else {
            $date = null; //Null means "now" at persist time
        }

        $reflectionClass = new \ReflectionClass($entity);
        $property = $reflectionClass->getProperty('addedDate');
        $property->setAccessible(true);
        $property->setValue($entity, $date);
    }
}