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

use App\Services\Attachments\FileTypeFilterTools;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FileTypeFilterToolsTest extends WebTestCase
{
    protected static $service;

    public static function setUpBeforeClass(): void
    {
        self::bootKernel();
        self::$service = self::getContainer()->get(FileTypeFilterTools::class);
    }

    public function validateDataProvider(): \Iterator
    {
        yield ['', true];
        //Empty string is valid
        yield ['.jpeg,.png, .gif', true];
        //Only extensions are valid
        yield ['image/*, video/*, .mp4, video/x-msvideo, application/vnd.amazon.ebook', true];
        yield ['application/vnd.amazon.ebook, audio/opus', true];
        yield ['*.notvalid, .png', false];
        //No stars in extension
        yield ['test.png', false];
        //No full filename
        yield ['application/*', false];
        //Only certain placeholders are allowed
        yield ['.png;.png,.jpg', false];
        //Wrong separator
        yield ['.png .jpg .gif', false];
    }

    public function normalizeDataProvider(): \Iterator
    {
        yield ['', ''];
        yield ['.jpeg,.png,.gif', '.jpeg,.png,.gif'];
        yield ['.jpeg, .png,    .gif,', '.jpeg,.png,.gif'];
        yield ['jpg, *.gif', '.jpg,.gif'];
        yield ['video, image/', 'video/*,image/*'];
        yield ['video/*', 'video/*'];
        yield ['video/x-msvideo,.jpeg', 'video/x-msvideo,.jpeg'];
        yield ['.video', '.video'];
        //Remove duplicate entries
        yield ['png, .gif, .png,', '.png,.gif'];
    }

    public function extensionAllowedDataProvider(): \Iterator
    {
        yield ['', 'txt', true];
        yield ['', 'everything_should_match', true];
        yield ['.jpg,.png', 'jpg', true];
        yield ['.jpg,.png', 'png', true];
        yield ['.jpg,.png', 'txt', false];
        yield ['image/*', 'jpeg', true];
        yield ['image/*', 'png', true];
        yield ['image/*', 'txt', false];
        yield ['application/pdf,.txt', 'pdf', true];
        yield ['application/pdf,.txt', 'txt', true];
        yield ['application/pdf,.txt', 'jpg', false];
    }

    /**
     * Test the validateFilterString method.
     *
     * @dataProvider validateDataProvider
     */
    public function testValidateFilterString(string $filter, bool $expected): void
    {
        $this->assertSame($expected, self::$service->validateFilterString($filter));
    }

    /**
     * @dataProvider normalizeDataProvider
     */
    public function testNormalizeFilterString(string $filter, string $expected): void
    {
        $this->assertSame($expected, self::$service->normalizeFilterString($filter));
    }

    /**
     * @dataProvider extensionAllowedDataProvider
     */
    public function testIsExtensionAllowed(string $filter, string $extension, bool $expected): void
    {
        $this->assertSame($expected, self::$service->isExtensionAllowed($filter, $extension));
    }
}
