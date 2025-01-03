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
namespace App\Doctrine\Types;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Type;

/**
 * A type to use for tinyint columns in MySQL
 */
class TinyIntType extends Type
{

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        //MySQL knows the TINYINT type directly
        //We do not use the TINYINT for sqlite, as it will be resolved to a BOOL type and bring problems with migrations
        if ($platform instanceof AbstractMySQLPlatform ) {
            //Use TINYINT(1) to allow for proper migration diffs
            return 'TINYINT(1)';
        }

        //For other platforms, we use the smallest integer type available
        return $platform->getSmallIntTypeDeclarationSQL($column);
    }

    public function getName(): string
    {
        return 'tinyint';
    }

    /**
     * {@inheritDoc}
     *
     * @param T $value
     *
     * @return (T is null ? null : int)
          *
     * @template T
     */
    public function convertToPHPValue($value, AbstractPlatform $platform): ?int
    {
        return $value === null ? null : (int) $value;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        //We use the comment, so that doctrine migrations can properly detect, that nothing has changed and no migration is needed.
        return true;
    }
}
