<?php
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

use App\Services\Attachments\FileTypeFilterTools;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FileTypeFilterToolsTest extends WebTestCase
{
    protected static $service;

    public static function setUpBeforeClass() : void
    {
        self::bootKernel();
        self::$service = self::$container->get(FileTypeFilterTools::class);
    }

    public function validateDataProvider(): array
    {
        return [
            ['', true], //Empty string is valid
            ['.jpeg,.png, .gif', true], //Only extensions are valid
            ['image/*, video/*, .mp4, video/x-msvideo, application/vnd.amazon.ebook', true],
            ['application/vnd.amazon.ebook, audio/opus', true],

            ['*.notvalid, .png', false], //No stars in extension
            ['test.png', false], //No full filename
            ['application/*', false], //Only certain placeholders are allowed
            ['.png;.png,.jpg', false], //Wrong separator
            ['.png .jpg .gif', false],
        ];
    }

    public function normalizeDataProvider(): array
    {
        return [
            ['', ''],
            ['.jpeg,.png,.gif', '.jpeg,.png,.gif'],
            ['.jpeg, .png,    .gif,', '.jpeg,.png,.gif'],
            ['jpg, *.gif', '.jpg,.gif'],
            ['video, image/', 'video/*,image/*'],
            ['video/*', 'video/*'],
            ['video/x-msvideo,.jpeg', 'video/x-msvideo,.jpeg'],
            ['.video', '.video'],
            //Remove duplicate entries
            ['png, .gif, .png,', '.png,.gif'],
        ];
    }

    public function extensionAllowedDataProvider(): array
    {
        return [
            ['', 'txt', true],
            ['', 'everything_should_match', true],

            ['.jpg,.png', 'jpg', true],
            ['.jpg,.png', 'png', true],
            ['.jpg,.png', 'txt', false],

            ['image/*', 'jpeg', true],
            ['image/*', 'png', true],
            ['image/*', 'txt', false],

            ['application/pdf,.txt', 'pdf', true],
            ['application/pdf,.txt', 'txt', true],
            ['application/pdf,.txt', 'jpg', false],
        ];
    }

    /**
     * Test the validateFilterString method.
     *
     * @dataProvider validateDataProvider
     */
    public function testValidateFilterString(string $filter, bool $expected)
    {
        $this->assertEquals($expected, self::$service->validateFilterString($filter));
    }

    /**
     * @dataProvider normalizeDataProvider
     */
    public function testNormalizeFilterString(string $filter, string $expected)
    {
        $this->assertEquals($expected, self::$service->normalizeFilterString($filter));
    }

    /**
     * @dataProvider extensionAllowedDataProvider
     */
    public function testIsExtensionAllowed(string $filter, string $extension, bool $expected)
    {
        $this->assertEquals($expected, self::$service->isExtensionAllowed($filter, $extension), $expected);
    }
}
