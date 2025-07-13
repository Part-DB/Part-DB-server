<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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
namespace App\Tests\DataTables\Filters\Constraints;

use PHPUnit\Framework\Attributes\DataProvider;
use App\DataTables\Filters\Constraints\FilterTrait;
use PHPUnit\Framework\TestCase;

class FilterTraitTest extends TestCase
{
    use FilterTrait;

    public function testUseHaving(): void
    {
        $this->assertFalse($this->useHaving);

        $this->useHaving();
        $this->assertTrue($this->useHaving);

        $this->useHaving(false);
        $this->assertFalse($this->useHaving);
    }

    public function isAggregateFunctionStringDataProvider(): iterable
    {
        yield [false, 'parts.test'];
        yield [false, 'attachments.test'];
        yield [true, 'COUNT(attachments)'];
        yield [true, 'MAX(attachments.value)'];
    }

    #[DataProvider('isAggregateFunctionStringDataProvider')]
    public function testIsAggregateFunctionString(bool $expected, string $input): void
    {
        $this->assertEquals($expected, $this->isAggregateFunctionString($input));
    }

}
