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


namespace App\Tests\Doctrine\Functions;

use App\Doctrine\Functions\ArrayPosition;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

final class ArrayPositionTest extends AbstractDoctrineFunctionTestCase
{
    public function testArrayPositionBuildsSql(): void
    {
        $function = new ArrayPosition('ARRAY_POSITION');
        $this->setObjectProperty($function, 'array', $this->createNode(':ids'));
        $this->setObjectProperty($function, 'field', $this->createNode('p.id'));

        $sql = $function->getSql($this->createSqlWalker(new PostgreSQLPlatform()));

        $this->assertSame('ARRAY_POSITION(:ids, p.id)', $sql);
    }
}

