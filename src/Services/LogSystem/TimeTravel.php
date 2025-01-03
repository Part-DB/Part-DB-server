<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Services\LogSystem;

use App\Entity\Attachments\AttachmentType;
use App\Entity\Base\AbstractDBElement;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Contracts\TimeStampableInterface;
use App\Entity\Contracts\TimeTravelInterface;
use App\Entity\LogSystem\AbstractLogEntry;
use App\Entity\LogSystem\CollectionElementDeleted;
use App\Entity\LogSystem\ElementEditedLogEntry;
use App\Repository\LogEntryRepository;
use Brick\Math\BigDecimal;
use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\MappingException;
use Exception;
use InvalidArgumentException;
use ReflectionClass;
use Symfony\Component\PropertyAccess\PropertyAccessor;

class TimeTravel
{
    protected LogEntryRepository $repo;

    public function __construct(protected EntityManagerInterface $em)
    {
        $this->repo = $em->getRepository(AbstractLogEntry::class);
    }


    /**
     * Undeletes the element with the given ID.
     *
     * @template T of AbstractDBElement
     * @param string $class The class name of the element that should be undeleted
     * @phpstan-param class-string<T> $class
     * @param int    $id    the ID of the element that should be undeleted
     * @phpstan-return T
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
     * @throws Exception
     */
    public function revertEntityToTimestamp(AbstractDBElement $element, \DateTimeInterface $timestamp, array $reverted_elements = []): void
    {
        if (!$element instanceof TimeStampableInterface) {
            throw new InvalidArgumentException('$element must have a Timestamp!');
        }

        if ($timestamp > new DateTime('now')) {
            throw new InvalidArgumentException('You can not travel to the future (yet)...');
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
        $metadata = $this->em->getClassMetadata($element::class);
        $associations = $metadata->getAssociationMappings();
        foreach ($associations as $field => $mapping) {
            if (
                ($element instanceof AbstractStructuralDBElement && ('parts' === $field || 'children' === $field))
                || ($element instanceof AttachmentType && 'attachments' === $field)
            ) {
                continue;
            }

            //Revert many-to-one association (one element in property)
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
                && !$mapping['isOwningSide']
            ) {
                $target_elements = $this->getField($element, $field);
                if (null === $target_elements || (is_countable($target_elements) ? count($target_elements) : 0) > 10) {
                    continue;
                }
                foreach ($target_elements as $target_element) {
                    if (null !== $target_element && $element->getLastModified() >= $timestamp) {
                        //Remove the element from collection, if it did not exist at $timestamp
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
     * This function decodes the array which is created during the json_encode of a datetime object and returns a DateTime object.
     * @param  array  $input
     * @return \DateTimeInterface
     * @throws Exception
     */
    private function dateTimeDecode(?array $input, string $doctrineType): ?\DateTimeInterface
    {
        //Allow null values
        if ($input === null) {
            return null;
        }

        //Mutable types
        if (in_array($doctrineType, [Types::DATETIME_MUTABLE, Types::DATE_MUTABLE], true)) {
            return new \DateTime($input['date'], new \DateTimeZone($input['timezone']));
        }
        //Immutable types
        if (in_array($doctrineType, [Types::DATETIME_IMMUTABLE, Types::DATE_IMMUTABLE], true)) {
            return new \DateTimeImmutable($input['date'], new \DateTimeZone($input['timezone']));
        }

        throw new InvalidArgumentException('The given doctrine type is not a datetime type!');
    }

    /**
     * Apply the changeset in the given LogEntry to the element.
     *
     * @throws MappingException
     */
    public function applyEntry(AbstractDBElement $element, TimeTravelInterface $logEntry): void
    {
        //Skip if this does not provide any info...
        if (!$logEntry->hasOldDataInformation()) {
            return;
        }
        if (!$element instanceof TimeStampableInterface) {
            return;
        }
        $metadata = $this->em->getClassMetadata($element::class);
        $old_data = $logEntry->getOldData();

        foreach ($old_data as $field => $data) {

            //We use the fieldMappings property directly instead of the hasField method, as we do not want to match the embedded field itself
            //The sub fields are handled in the setField method
            if (isset($metadata->fieldMappings[$field])) {
                //We need to convert the string to a BigDecimal first
                if (!$data instanceof BigDecimal && ('big_decimal' === $metadata->getFieldMapping($field)->type)) {
                    $data = BigDecimal::of($data);
                }

                if (!$data instanceof \DateTimeInterface
                    && (in_array($metadata->getFieldMapping($field)->type,
                        [
                            Types::DATETIME_IMMUTABLE,
                            Types::DATETIME_IMMUTABLE,
                            Types::DATE_MUTABLE,
                            Types::DATETIME_IMMUTABLE
                        ], true))) {
                    $data = $this->dateTimeDecode($data, $metadata->getFieldMapping($field)->type);
                }

                $this->setField($element, $field, $data);
            }
            if ($metadata->hasAssociation($field)) {
                $mapping = $metadata->getAssociationMapping($field);
                $target_class = $mapping['targetEntity'];
                //Try to extract the old ID:
                if (is_array($data) && isset($data['@id'])) {
                    $entity = $this->em->getReference($target_class, $data['@id']);
                    $this->setField($element, $field, $entity);
                }
            }
        }

        $this->setField($element, 'lastModified', $logEntry->getTimestamp());
    }

    protected function getField(AbstractDBElement $element, string $field): mixed
    {
        $reflection = new ReflectionClass($element::class);
        $property = $reflection->getProperty($field);

        return $property->getValue($element);
    }

    /**
     * @param int|null|object $new_value
     */
    protected function setField(AbstractDBElement $element, string $field, mixed $new_value): void
    {
        //If the field name contains a dot, it is a embeddedable object and we need to split the field name
        if (str_contains($field, '.')) {
            [$embedded, $embedded_field] = explode('.', $field);

            $elementClass = new ReflectionClass($element::class);
            $property = $elementClass->getProperty($embedded);
            $embeddedClass = $property->getValue($element);

            $embeddedReflection = new ReflectionClass($embeddedClass::class);
            $property = $embeddedReflection->getProperty($embedded_field);
            $target_element = $embeddedClass;
        } else {
            $reflection = new ReflectionClass($element::class);
            $property = $reflection->getProperty($field);
            $target_element = $element;
        }

        //Check if the property is an BackedEnum, then convert the int or float value to an enum instance
        if ((is_string($new_value) || is_int($new_value))
            && $property->getType() instanceof \ReflectionNamedType
            && is_a($property->getType()->getName(), \BackedEnum::class, true)) {
            /** @phpstan-var class-string<\BackedEnum> $enum_class */
            $enum_class = $property->getType()->getName();
            $new_value = $enum_class::from($new_value);
        }

        $property->setValue($target_element, $new_value);
    }
}
