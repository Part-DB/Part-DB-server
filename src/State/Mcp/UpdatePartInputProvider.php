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

namespace App\State\Mcp;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Mcp\DTO\UpdatePartInput;

/**
 * Builds an UpdatePartInput directly from the raw MCP tool-call arguments (see CreatePartInputProvider for why
 * this bypasses the default MCP ToolProvider). This is required, not just preferred, for update_part: only by
 * reading the raw arguments array can "omitted" and "explicitly null" be told apart, which is what
 * UpdatePartInput::wasProvided() needs for correct partial-update semantics.
 */
final class UpdatePartInputProvider implements ProviderInterface
{
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): UpdatePartInput
    {
        $data = $context['mcp_data'] ?? [];
        if (!is_array($data)) {
            throw new \InvalidArgumentException('Expected the MCP tool arguments to be an object.');
        }

        return UpdatePartInput::fromArray($data);
    }
}
