<?php

declare(strict_types=1);

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
namespace App\Entity\LogSystem;

enum PartStockChangeType: string
{
    case ADD = "add";
    case WITHDRAW = "withdraw";
    case MOVE = "move";

    /**
     * Converts the type to a short representation usable in the extra field of the log entry.
     * @return string
     */
    public function toExtraShortType(): string
    {
        return match ($this) {
            self::ADD => 'a',
            self::WITHDRAW => 'w',
            self::MOVE => 'm',
        };
    }

    public function toTranslationKey(): string
    {
        return 'log.part_stock_changed.' . $this->value;
    }

    public static function fromExtraShortType(string $value): self
    {
        return match ($value) {
            'a' => self::ADD,
            'w' => self::WITHDRAW,
            'm' => self::MOVE,
            default => throw new \InvalidArgumentException("Invalid short type: $value"),
        };
    }
}
