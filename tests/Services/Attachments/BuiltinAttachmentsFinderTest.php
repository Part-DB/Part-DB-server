<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Tests\Services\Attachments;

use PHPUnit\Framework\Attributes\DataProvider;
use App\Services\Attachments\BuiltinAttachmentsFinder;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BuiltinAttachmentsFinderTest extends WebTestCase
{
    protected static array $mock_list = [
        '%FOOTPRINTS%/test/test.jpg', '%FOOTPRINTS%/test/test.png', '%FOOTPRINTS%/123.jpg', '%FOOTPRINTS%/123.jpeg',
        '%FOOTPRINTS_3D%/test.jpg', '%FOOTPRINTS_3D%/hallo.txt',
    ];
    /**
     * @var BuiltinAttachmentsFinder
     */
    protected static $service;

    public static function setUpBeforeClass(): void
    {
        //Get a service instance.
        self::bootKernel();
        self::$service = self::getContainer()->get(BuiltinAttachmentsFinder::class);
    }

    public function dataProvider(): \Iterator
    {
        //No value should return empty array
        yield ['', [], []];
        yield ['', ['empty_returns_all' => true], static::$mock_list];
        //Basic search for keyword
        yield ['test', [], ['%FOOTPRINTS%/test/test.jpg', '%FOOTPRINTS%/test/test.png', '%FOOTPRINTS_3D%/test.jpg']];
        yield ['%FOOTPRINTS_3D%', [], ['%FOOTPRINTS_3D%/test.jpg', '%FOOTPRINTS_3D%/hallo.txt']];
        yield ['.txt', [], ['%FOOTPRINTS_3D%/hallo.txt']];
    }

    #[DataProvider('dataProvider')]
    public function testFind($keyword, $options, $expected): void
    {
        $value = static::$service->find($keyword, $options, static::$mock_list);
        //$this->assertEquals($expected, static::$service->find($keyword, $options, static::$mock_list));
        $this->assertSame([], array_diff($value, $expected), 'Additional');
        $this->assertSame([], array_diff($expected, $value), 'Missing:');
    }
}
