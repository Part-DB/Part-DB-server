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

/**
 * Shared helpers to pull loosely-typed scalar values out of the raw MCP tool-call arguments array (which, unlike
 * REST's Serializer-based deserialization, gives us plain PHP arrays with no type coercion at all).
 *
 * All accessors return null both when the key is absent AND when it is explicitly null - the two cases are
 * intentionally not distinguished here. Callers that need "omitted vs. explicitly null" (i.e. update_part's
 * partial-update semantics) must track presence separately via array_key_exists()/array_keys().
 */
final class PartInputHelpers
{
    private function __construct()
    {
        //This class only contains static helpers and must not be instantiated
    }

    public static function str(array $data, string $key): ?string
    {
        if (!array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }

        return (string) $data[$key];
    }

    public static function int(array $data, string $key): ?int
    {
        if (!array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }

        return (int) $data[$key];
    }

    public static function float(array $data, string $key): ?float
    {
        if (!array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }

        return (float) $data[$key];
    }

    public static function bool(array $data, string $key): ?bool
    {
        if (!array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }

        return (bool) $data[$key];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function arr(array $data, string $key): array
    {
        return is_array($data[$key] ?? null) ? $data[$key] : [];
    }
}
