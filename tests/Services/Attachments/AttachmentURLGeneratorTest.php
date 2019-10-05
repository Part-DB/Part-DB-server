<?php
/**
 *
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/
 *
 * part-db version 0.2+
 * Copyright (C) 2009 K. Jacobs and others (see authors.php)
 * http://code.google.com/p/part-db/
 *
 * Part-DB Version 0.4+
 * Copyright (C) 2016 - 2019 Jan Böhmer
 * https://github.com/jbtronics
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
 *
 */

namespace App\Tests\Services\Attachments;


use App\Services\Attachments\AttachmentURLGenerator;
use App\Services\BuiltinAttachmentsFinder;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AttachmentURLGeneratorTest extends WebTestCase
{
    protected const PUBLIC_DIR = "/public";

    protected static $service;

    public static function setUpBeforeClass()
    {
        //Get an service instance.
        self::bootKernel();
        self::$service = self::$container->get(AttachmentURLGenerator::class);
    }

    public function dataProvider()
    {
        return [
            ['/public/test.jpg', 'test.jpg'],
            ['/public/folder/test.jpg', 'folder/test.jpg'],
            ['/not/public/test.jpg', null],
            ['/public/', ''],
            ['not/absolute/test.jpg', null]
        ];
    }

    /**
     * @dataProvider dataProvider
     * @param $input
     * @param $expected
     */
    public function testabsolutePathToAssetPath($input, $expected)
    {
        $this->assertEquals($expected, static::$service->absolutePathToAssetPath($input, static::PUBLIC_DIR));
    }
}