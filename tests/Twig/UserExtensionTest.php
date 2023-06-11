<?php
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

namespace App\Tests\Twig;

use App\Twig\UserExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UserExtensionTest extends WebTestCase
{
    protected $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(UserExtension::class);
    }

    public function removeeLocaleFromPathDataSet(): ?\Generator
    {
        yield ['/', '/de/'];
        yield ['/test', '/de/test'];
        yield ['/test/foo', '/en/test/foo'];
        yield ['/test/foo/bar?param1=val1&param2=val2', '/en/test/foo/bar?param1=val1&param2=val2'];
    }

    /**
     * @dataProvider removeeLocaleFromPathDataSet
     */
    public function testRemoveLocaleFromPath(string $expected, string $input): void
    {
        $this->assertSame($expected, $this->service->removeLocaleFromPath($input));
    }

    public function testRemoveLocaleFromPathException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->removeLocaleFromPath('/part/info/1');
    }
}
