<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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
namespace App\Tests\Helpers;

use App\Helpers\IPAnonymizer;
use PHPUnit\Framework\TestCase;

class IPAnonymizerTest extends TestCase
{

    public function anonymizeDataProvider(): \Generator
    {
        yield ['127.0.0.0', '127.0.0.23'];
        yield ['2001:db8:85a3::', '2001:0db8:85a3:0000:0000:8a2e:0370:7334'];
        //RFC 4007 format
        yield ['fe80::', 'fe80::1fc4:15d8:78db:2319%enp4s0'];
    }

    /**
     * @dataProvider anonymizeDataProvider
     */
    public function testAnonymize(string $expected, string $input): void
    {
        $this->assertSame($expected, IPAnonymizer::anonymize($input));
    }
}
