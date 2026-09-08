<?php
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

declare(strict_types=1);

namespace App\Services\InfoProviderSystem;

use App\ApiResource\InfoProviderSystem\PartFieldChange;
use App\Entity\Base\AbstractNamedDBElement;
use App\Entity\Parts\Part;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;

/**
 * Determines which fields of a part an info provider update actually changed.
 *
 * The change list is derived from Doctrine's original entity data without asking the UnitOfWork to compute
 * changesets. Computing them before flush would cascade-persist newly merged collection items and corrupt their
 * insert changesets when Doctrine computes them a second time during flush.
 */
final readonly class PartChangeSetBuilder
{
    /**
     * @var string[] Bookkeeping fields which change on every single update and would only add noise
     */
    private const IGNORED_FIELDS = ['lastModified', 'providerReference.last_updated'];

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return PartFieldChange[] The changes pending on the given part, in a stable (alphabetical) order.
     *                           Empty for a detached part.
     */
    public function build(Part $part): array
    {
        $uow = $this->entityManager->getUnitOfWork();
        $is_new = $uow->isScheduledForInsert($part);

        if (!$is_new && !$this->entityManager->contains($part)) {
            return [];
        }

        $metadata = $this->entityManager->getClassMetadata($part::class);
        $original_data = $uow->getOriginalEntityData($part);
        $changes = [];

        foreach ($metadata->getFieldNames() as $field) {
            if ($metadata->isIdentifier($field) || in_array($field, self::IGNORED_FIELDS, true)) {
                continue;
            }

            if (!$is_new && !array_key_exists($field, $original_data)) {
                continue;
            }

            $old = $is_new ? null : $original_data[$field];
            $new = $metadata->getFieldValue($part, $field);
            $this->addChange($changes, $field, $old, $new);
        }

        foreach ($metadata->getAssociationNames() as $field) {
            if (!$metadata->isSingleValuedAssociation($field)
                || !$metadata->getAssociationMapping($field)->isOwningSide()) {
                continue;
            }

            if (!$is_new && !array_key_exists($field, $original_data)) {
                continue;
            }

            $old = $is_new ? null : $original_data[$field];
            $new = $metadata->getFieldValue($part, $field);
            $this->addChange($changes, $field, $old, $new);
        }

        foreach ($this->collectionChanges($part, $is_new) as $field => $change) {
            $changes[$field] = $change;
        }

        ksort($changes);

        return array_values($changes);
    }

    /** @param array<string, PartFieldChange> $changes */
    private function addChange(array &$changes, string $field, mixed $old, mixed $new): void
    {
        $old_value = $this->stringify($old);
        $new_value = $this->stringify($new);

        if ($old_value !== $new_value) {
            $changes[$field] = new PartFieldChange($field, $old_value, $new_value);
        }
    }

    /**
     * Reports added and removed items of the part's own collections (attachments, orderdetails, parameters, ...)
     * as a change of the collection's size, which keeps the response to a single, simple shape.
     * @return array<string, PartFieldChange>
     */
    private function collectionChanges(Part $part, bool $is_new): array
    {
        $metadata = $this->entityManager->getClassMetadata($part::class);
        $changes = [];

        foreach ($metadata->getAssociationNames() as $field) {
            if (!$metadata->isCollectionValuedAssociation($field)) {
                continue;
            }

            $collection = $metadata->getFieldValue($part, $field);
            if (!$collection instanceof Collection) {
                continue;
            }

            if ($is_new) {
                $old_count = 0;
                $new_count = $collection->count();
                $added = $new_count;
                $removed = 0;
            } elseif ($collection instanceof PersistentCollection && $collection->getOwner() === $part) {
                $added = count($collection->getInsertDiff());
                $removed = count($collection->getDeleteDiff());
                $new_count = $collection->count();
                $old_count = $new_count - $added + $removed;
            } else {
                continue;
            }

            if ($added === 0 && $removed === 0 && !$this->collectionItemsChanged($collection)) {
                continue;
            }

            $changes[$field] = new PartFieldChange($field, (string) $old_count, (string) $new_count);
        }

        return $changes;
    }

    private function collectionItemsChanged(Collection $collection): bool
    {
        $visited = [];

        foreach ($collection as $item) {
            if (is_object($item) && $this->entityHasChanges($item, $visited)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, true> $visited */
    private function entityHasChanges(object $entity, array &$visited): bool
    {
        $object_id = spl_object_id($entity);
        if (isset($visited[$object_id])) {
            return false;
        }
        $visited[$object_id] = true;

        if (method_exists($entity, 'getID') && $entity->getID() === null) {
            return true;
        }

        if (!$this->entityManager->contains($entity)) {
            return false;
        }

        $metadata = $this->entityManager->getClassMetadata($entity::class);
        $original_data = $this->entityManager->getUnitOfWork()->getOriginalEntityData($entity);

        foreach ($metadata->getFieldNames() as $field) {
            if ($metadata->isIdentifier($field)
                || in_array($field, self::IGNORED_FIELDS, true)
                || !array_key_exists($field, $original_data)) {
                continue;
            }

            if ($this->stringify($original_data[$field])
                !== $this->stringify($metadata->getFieldValue($entity, $field))) {
                return true;
            }
        }

        foreach ($metadata->getAssociationNames() as $field) {
            $association = $metadata->getAssociationMapping($field);
            $value = $metadata->getFieldValue($entity, $field);

            if ($metadata->isSingleValuedAssociation($field)) {
                if ($association->isOwningSide()
                    && array_key_exists($field, $original_data)
                    && $this->stringify($original_data[$field]) !== $this->stringify($value)) {
                    return true;
                }
                continue;
            }

            if (!$value instanceof Collection) {
                continue;
            }

            if ($value instanceof PersistentCollection) {
                if (count($value->getInsertDiff()) > 0 || count($value->getDeleteDiff()) > 0) {
                    return true;
                }
                if (!$value->isInitialized()) {
                    continue;
                }
            }

            foreach ($value as $item) {
                if (is_object($item) && $this->entityHasChanges($item, $visited)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Renders a raw property value as a string, so that every kind of field fits the same response schema.
     */
    private function stringify(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_bool($value) => $value ? 'true' : 'false',
            $value instanceof \DateTimeInterface => $value->format(\DateTimeInterface::ATOM),
            $value instanceof \BackedEnum => (string) $value->value,
            $value instanceof AbstractNamedDBElement => $value->getName(),
            is_object($value) => method_exists($value, 'getId') ? '#'.$value->getId() : $value::class,
            is_array($value) => json_encode($value, JSON_THROW_ON_ERROR),
            default => (string) $value,
        };
    }
}
