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

namespace App\ApiResource\InfoProviderSystem;

use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

/**
 * One field of a part that an info provider update changed (or would change, on a dry run).
 * Values are rendered as strings, so that a single, simple schema can describe every kind of field
 * (numbers, dates, related entities, enums and collection sizes alike).
 */
#[Context(normalizationContext: [AbstractObjectNormalizer::SKIP_NULL_VALUES => false])]
readonly class PartFieldChange
{
    public function __construct(
        /** @var string The name of the changed property, e.g. "name", "mass" or "attachments" */
        #[Groups(['info_provider_write:read'])]
        public string $field,

        /** @var string|null The value before the update, or null if it was not set */
        #[Groups(['info_provider_write:read'])]
        public ?string $old,

        /** @var string|null The value after the update, or null if it is not set anymore */
        #[Groups(['info_provider_write:read'])]
        public ?string $new,
    ) {
    }
}
