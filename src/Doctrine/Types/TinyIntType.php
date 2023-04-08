<?php
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

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * A type to use for tinyint columns in MySQL
 */
class TinyIntType extends Type
{

    public function getSQLDeclaration(array $column, AbstractPlatform $platform)
    {
        return 'TINYINT';
    }

    public function getName()
    {
        return 'tinyint';
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform)
    {
        //We use the comment, so that doctrine migrations can properly detect, that nothing has changed and no migration is needed.
        return true;
    }
}