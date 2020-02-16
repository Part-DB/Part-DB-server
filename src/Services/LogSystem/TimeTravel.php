<?php
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


use App\Entity\Base\AbstractDBElement;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Contracts\TimeStampableInterface;
use App\Entity\Contracts\TimeTravelInterface;
use App\Entity\LogSystem\AbstractLogEntry;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Proxies\__CG__\App\Entity\Attachments\AttachmentType;

class TimeTravel
{
    protected $em;
    protected $repo;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->repo = $em->getRepository(AbstractLogEntry::class);
    }

    public function revertEntityToTimestamp(AbstractDBElement $element, \DateTime $timestamp, array $reverted_elements = [])
    {
        if (!$element instanceof TimeStampableInterface) {
            throw new \InvalidArgumentException('$element must have a Timestamp!');
        }

        if ($timestamp > new \DateTime('now')) {
            throw new \InvalidArgumentException('You can not travel to the future (yet)...');
        }

        //Skip this process if already were reverted...
        if (in_array($element, $reverted_elements)) {
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
            $this->applyEntry($element, $logEntry);
        }

        // Revert any of the associated elements
        $metadata = $this->em->getClassMetadata(get_class($element));
        $associations = $metadata->getAssociationMappings();
        foreach ($associations as $field => $mapping) {
            if (
                ($element instanceof AbstractStructuralDBElement && ($field === 'parts' || $field === 'children'))
                || ($element instanceof AttachmentType && $field === 'attachments')
            ) {
                continue;
            }


            //Revert many to one association
            if (
                $mapping['type'] === ClassMetadata::MANY_TO_ONE
                || $mapping['type'] === ClassMetadata::ONE_TO_ONE
            ) {
                $target_element = $this->getField($element, $field);
                if ($target_element !== null && $element->getLastModified() > $timestamp) {
                    $this->revertEntityToTimestamp($target_element, $timestamp, $reverted_elements);
                }
            } elseif (
                ($mapping['type'] === ClassMetadata::MANY_TO_MANY
                    || $mapping['type'] === ClassMetadata::ONE_TO_MANY)
                && $mapping['isOwningSide'] === false
            ) {
                $target_elements = $this->getField($element, $field);
                if ($target_elements === null || count($target_elements) > 10) {
                    continue;
                }
                foreach ($target_elements as $target_element) {
                    if ($target_element !== null && $element->getLastModified() > $timestamp) {
                        //Remove the element from collection, if it did not existed at $timestamp
                        if (!$this->repo->getElementExistedAtTimestamp($target_element, $timestamp)) {
                            if ($target_elements instanceof Collection) {
                                $target_elements->removeElement($target_element);
                            }
                        }
                        $this->revertEntityToTimestamp($target_element, $timestamp, $reverted_elements);
                    }
                }
            }

        }
    }

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
                $this->setField($element, $field, $data);
            }
            if ($metadata->hasAssociation($field)) {
                $target_class = $metadata->getAssociationMapping($field)['targetEntity'];
                $target_id = null;
                //Try to extract the old ID:
                if (is_array($data) && isset($data['@id'])) {
                    $target_id = $data['@id'];
                } else {
                    throw new \RuntimeException('The given $logEntry contains invalid informations!');
                }
                $entity = $this->em->getPartialReference($target_class, $target_id);
                $this->setField($element, $field, $entity);
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

    protected function setField(AbstractDBElement $element, string $field, $new_value)
    {
        $reflection = new \ReflectionClass(get_class($element));
        $property = $reflection->getProperty($field);
        $property->setAccessible(true);
        $property->setValue($element, $new_value);
    }
}