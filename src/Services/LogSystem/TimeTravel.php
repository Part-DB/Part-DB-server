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

namespace App\Services\LogSystem;

use App\Entity\Attachments\AttachmentType;
use App\Entity\Base\AbstractDBElement;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Contracts\TimeStampableInterface;
use App\Entity\Contracts\TimeTravelInterface;
use App\Entity\LogSystem\AbstractLogEntry;
use App\Entity\LogSystem\CollectionElementDeleted;
use App\Entity\LogSystem\ElementEditedLogEntry;
use Brick\Math\BigDecimal;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;

class TimeTravel
{
    protected $em;
    protected $repo;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->repo = $em->getRepository(AbstractLogEntry::class);
    }

    /**
     * Undeletes the element with the given ID.
     *
     * @param string $class The class name of the element that should be undeleted
     * @param int    $id    the ID of the element that should be undeleted
     */
    public function undeleteEntity(string $class, int $id): AbstractDBElement
    {
        $log = $this->repo->getUndeleteDataForElement($class, $id);
        $element = new $class();
        $this->applyEntry($element, $log);

        //Set internal ID so the element can be reverted
        $this->setField($element, 'id', $id);

        //Let database determine when it will be created
        $this->setField($element, 'addedDate', null);

        return $element;
    }

    /**
     * Revert the given element to the state it has on the given timestamp.
     *
     * @param AbstractLogEntry[] $reverted_elements
     *
     * @throws \Exception
     */
    public function revertEntityToTimestamp(AbstractDBElement $element, \DateTime $timestamp, array $reverted_elements = []): void
    {
        if (!$element instanceof TimeStampableInterface) {
            throw new \InvalidArgumentException('$element must have a Timestamp!');
        }

        if ($timestamp > new \DateTime('now')) {
            throw new \InvalidArgumentException('You can not travel to the future (yet)...');
        }

        //Skip this process if already were reverted...
        if (in_array($element, $reverted_elements, true)) {
            return;
        }
        $reverted_elements[] = $element;

        $history = $this->repo->getTimetravelDataForElement($element, $timestamp);

        /*
        if (!$this->repo->getElementExistedAtTimestamp($element, $timestamp)) {
            $element = null;
            return;
        }*/

        foreach ($history as $logEntry) {
            if ($logEntry instanceof ElementEditedLogEntry) {
                $this->applyEntry($element, $logEntry);
            }
            if ($logEntry instanceof CollectionElementDeleted) {
                //Undelete element and add it to collection again
                $undeleted = $this->undeleteEntity(
                    $logEntry->getDeletedElementClass(),
                    $logEntry->getDeletedElementID()
                );
                if ($this->repo->getElementExistedAtTimestamp($undeleted, $timestamp)) {
                    $this->revertEntityToTimestamp($undeleted, $timestamp, $reverted_elements);
                    $collection = $this->getField($element, $logEntry->getCollectionName());
                    if ($collection instanceof Collection) {
                        $collection->add($undeleted);
                    }
                }
            }
        }

        // Revert any of the associated elements
        $metadata = $this->em->getClassMetadata(get_class($element));
        $associations = $metadata->getAssociationMappings();
        foreach ($associations as $field => $mapping) {
            if (
                ($element instanceof AbstractStructuralDBElement && ('parts' === $field || 'children' === $field))
                || ($element instanceof AttachmentType && 'attachments' === $field)
            ) {
                continue;
            }

            //Revert many to one association (one element in property)
            if (
                ClassMetadata::MANY_TO_ONE === $mapping['type']
                || ClassMetadata::ONE_TO_ONE === $mapping['type']
            ) {
                $target_element = $this->getField($element, $field);
                if (null !== $target_element && $element->getLastModified() > $timestamp) {
                    $this->revertEntityToTimestamp($target_element, $timestamp, $reverted_elements);
                }
            } elseif ( //Revert *_TO_MANY associations (collection properties)
                (ClassMetadata::MANY_TO_MANY === $mapping['type']
                    || ClassMetadata::ONE_TO_MANY === $mapping['type'])
                && false === $mapping['isOwningSide']
            ) {
                $target_elements = $this->getField($element, $field);
                if (null === $target_elements || count($target_elements) > 10) {
                    continue;
                }
                foreach ($target_elements as $target_element) {
                    if (null !== $target_element && $element->getLastModified() >= $timestamp) {
                        //Remove the element from collection, if it did not existed at $timestamp
                        if (!$this->repo->getElementExistedAtTimestamp(
                                $target_element,
                                $timestamp
                            ) && $target_elements instanceof Collection) {
                            $target_elements->removeElement($target_element);
                        }
                        $this->revertEntityToTimestamp($target_element, $timestamp, $reverted_elements);
                    }
                }
            }
        }
    }

    /**
     * Apply the changeset in the given LogEntry to the element.
     *
     * @throws \Doctrine\ORM\Mapping\MappingException
     */
    public function applyEntry(AbstractDBElement $element, TimeTravelInterface $logEntry): void
    {
        //Skip if this does not provide any info...
        if (!$logEntry->hasOldDataInformations()) {
            return;
        }
        if (!$element instanceof TimeStampableInterface) {
            return;
        }
        $metadata = $this->em->getClassMetadata(get_class($element));
        $old_data = $logEntry->getOldData();

        foreach ($old_data as $field => $data) {
            if ($metadata->hasField($field)) {
                //We need to convert the string to a BigDecimal first
                if (!$data instanceof BigDecimal && ('big_decimal' === $metadata->getFieldMapping($field)['type'])) {
                    $data = BigDecimal::of($data);
                }

                $this->setField($element, $field, $data);
            }
            if ($metadata->hasAssociation($field)) {
                $mapping = $metadata->getAssociationMapping($field);
                $target_class = $mapping['targetEntity'];
                //Try to extract the old ID:
                if (is_array($data) && isset($data['@id'])) {
                    $entity = $this->em->getPartialReference($target_class, $data['@id']);
                    $this->setField($element, $field, $entity);
                }
            }
        }

        $this->setField($element, 'lastModified', $logEntry->getTimestamp());
    }

    protected function getField(AbstractDBElement $element, string $field)
    {
        $reflection = new \ReflectionClass(get_class($element));
        $property = $reflection->getProperty($field);
        $property->setAccessible(true);

        return $property->getValue($element);
    }

    /**
     * @param \DateTime|int|null $new_value
     */
    protected function setField(AbstractDBElement $element, string $field, $new_value): void
    {
        $reflection = new \ReflectionClass(get_class($element));
        $property = $reflection->getProperty($field);
        $property->setAccessible(true);

        $property->setValue($element, $new_value);
    }
}
