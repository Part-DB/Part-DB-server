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

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * Like Symfony's UniqueEntity, but for entities that live in an owning collection mapped with
 * orphanRemoval: true (e.g. Part::$parameters / Part::$attachments, both mappedBy 'element').
 *
 * Doctrine schedules such an entity for removal as soon as it is taken out of that collection (see
 * PersistentCollection::removeElement()), well before the actual DELETE is flushed. A plain UniqueEntity
 * check runs before that flush and would still find the not-yet-deleted row and report it as a conflict,
 * even though it is already gone from the owner's active collection (e.g. because it was replaced by a new
 * entity with the same unique value in the same request) and will never be flushed again.
 *
 * $ownerField names the association among $fields that points to the owning/parent entity (e.g. 'element').
 * The validator resolves that association's `inversedBy` from Doctrine's metadata to find the collection on
 * the owner (e.g. Part::$parameters) and ignores any match that has already been removed from it, without
 * requiring any interface or helper method on the validated entity or its owner.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class UniqueEntityIgnoringOrphans extends Constraint
{
    public const NOT_UNIQUE_ERROR = '2f9a6a2e-6b3b-4f7a-9c9e-2a6f7b6c9d3e';

    protected const ERROR_NAMES = [
        self::NOT_UNIQUE_ERROR => 'NOT_UNIQUE_ERROR',
    ];

    public string $message = 'This value is already used.';

    /**
     * @param string[] $fields     The combination of fields that must contain unique values
     * @param string   $ownerField Which of $fields is the Doctrine association pointing at the owning/parent entity
     * @param bool     $ignoreNull Whether a criteria value of null makes the check pass automatically
     * @param string|null $errorPath Bind the constraint violation to this field instead of the first one in $fields
     */
    public function __construct(
        public readonly array $fields,
        public readonly string $ownerField,
        ?string $message = null,
        public readonly bool $ignoreNull = true,
        public readonly ?string $errorPath = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);

        $this->message = $message ?? $this->message;
    }

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }

    public function validatedBy(): string
    {
        return UniqueEntityIgnoringOrphansValidator::class;
    }
}
