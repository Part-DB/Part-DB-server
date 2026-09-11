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

namespace App\Mcp\DTO;

use Symfony\Component\Validator\Constraints as Assert;

readonly class InfoProviderSearchInput
{
    public function __construct(
        /** @var string The keyword to search for (e.g. a part name, manufacturer product number or GTIN) */
        #[Assert\NotBlank]
        public string $keyword,
        /**
         * @var string[]|null The keys of the info providers to search in (e.g. "digikey", "mouser", "lcsc"). If not
         * given, the default search providers configured in the system settings are used.
         */
        #[Assert\All([new Assert\Type('string')])]
        public ?array $providers = null,
    ) {
    }
}
