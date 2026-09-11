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

use App\Doctrine\Functions\Field2;
use Doctrine\DBAL\Platforms\MySQLPlatform;

final class Field2Test extends AbstractDoctrineFunctionTestCase
{
    public function testField2BuildsSql(): void
    {
        $function = new Field2('FIELD2');
        $this->setObjectProperty($function, 'field', $this->createNode('p.id'));
        $this->setObjectProperty($function, 'values', [
            $this->createNode('1'),
            $this->createNode('2'),
            $this->createNode('3'),
        ]);

        $sql = $function->getSql($this->createSqlWalker(new MySQLPlatform()));

        $this->assertSame('FIELD2(p.id, 1, 2, 3)', $sql);
    }
}

