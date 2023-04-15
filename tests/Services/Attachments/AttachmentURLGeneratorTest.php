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

use App\Services\Attachments\AttachmentURLGenerator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AttachmentURLGeneratorTest extends WebTestCase
{
    protected const PUBLIC_DIR = '/public';

    protected static $service;

    public static function setUpBeforeClass(): void
    {
        //Get a service instance.
        self::bootKernel();
        self::$service = self::getContainer()->get(AttachmentURLGenerator::class);
    }

    public function dataProvider(): array
    {
        return [
            ['/public/test.jpg', 'test.jpg'],
            ['/public/folder/test.jpg', 'folder/test.jpg'],
            ['/not/public/test.jpg', null],
            ['/public/', ''],
            ['not/absolute/test.jpg', null],
        ];
    }

    /**
     * @dataProvider dataProvider
     *
     * @param $input
     * @param $expected
     */
    public function testTestabsolutePathToAssetPath($input, $expected): void
    {
        $this->assertSame($expected, static::$service->absolutePathToAssetPath($input, static::PUBLIC_DIR));
    }
}
