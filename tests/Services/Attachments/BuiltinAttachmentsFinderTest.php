<?php

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Tests\Services\Attachments;

use App\Services\Attachments\BuiltinAttachmentsFinder;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BuiltinAttachmentsFinderTest extends WebTestCase
{
    protected static $mock_list = [
        '%FOOTPRINTS%/test/test.jpg', '%FOOTPRINTS%/test/test.png', '%FOOTPRINTS%/123.jpg', '%FOOTPRINTS%/123.jpeg',
        '%FOOTPRINTS_3D%/test.jpg', '%FOOTPRINTS_3D%/hallo.txt',
    ];
    /**
     * @var BuiltinAttachmentsFinder
     */
    protected static $service;

    public static function setUpBeforeClass(): void
    {
        //Get an service instance.
        self::bootKernel();
        self::$service = self::$container->get(BuiltinAttachmentsFinder::class);
    }

    public function dataProvider()
    {
        return [
            //No value should return empty array
            ['', [], []],
            ['', ['empty_returns_all' => true], static::$mock_list],
            //Basic search for keyword
            ['test', [], ['%FOOTPRINTS%/test/test.jpg', '%FOOTPRINTS%/test/test.png', '%FOOTPRINTS_3D%/test.jpg']],
            ['%FOOTPRINTS_3D%', [], ['%FOOTPRINTS_3D%/test.jpg', '%FOOTPRINTS_3D%/hallo.txt']],
            ['.txt', [], ['%FOOTPRINTS_3D%/hallo.txt']],
            //Filter extensions
            //['test', ['allowed_extensions' => ['jpeg', 'jpg']], ['%FOOTPRINTS%/test/test.jpg', '%FOOTPRINTS%/123.jpeg', '%FOOTPRINTS_3D%/test.jpg']],
            //['test.jpg', ['allowed_extensions' => ['jpeg', 'jpg']], ['%FOOTPRINTS%/test/test.jpg', '%FOOTPRINTS_3D%/test.jpg']]
        ];
    }

    /**
     * @dataProvider dataProvider
     */
    public function testFind($keyword, $options, $expected): void
    {
        $value = static::$service->find($keyword, $options, static::$mock_list);
        //$this->assertEquals($expected, static::$service->find($keyword, $options, static::$mock_list));
        $this->assertSame([], array_diff($value, $expected), 'Additional');
        $this->assertSame([], array_diff($expected, $value), 'Missing:');
    }
}
