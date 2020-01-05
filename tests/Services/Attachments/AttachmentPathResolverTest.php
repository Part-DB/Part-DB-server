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

use App\Services\AmountFormatter;
use App\Services\Attachments\AttachmentPathResolver;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AttachmentPathResolverTest extends WebTestCase
{
    public static $media_path;
    public static $footprint_path;
    /**
     * @var AmountFormatter
     */
    protected static $service;
    protected static $projectDir_orig;
    protected static $projectDir;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        self::bootKernel();
        self::$projectDir_orig = realpath(self::$kernel->getProjectDir());
        self::$projectDir = str_replace('\\', '/', self::$projectDir_orig);
        self::$media_path = self::$projectDir.'/public/media';
        self::$footprint_path = self::$projectDir.'/public/img/footprints';
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        //Get an service instance.
        self::bootKernel();
        self::$service = self::$container->get(AttachmentPathResolver::class);
    }

    public function testParameterToAbsolutePath(): void
    {
        //If null is passed, null must be returned
        $this->assertNull(self::$service->parameterToAbsolutePath(null));

        //Absolute path should be returned like they are (we use projectDir here, because we know that this dir exists)
        $this->assertSame(self::$projectDir_orig, self::$service->parameterToAbsolutePath(self::$projectDir));

        //Relative pathes should be resolved
        $this->assertSame(self::$projectDir_orig.\DIRECTORY_SEPARATOR.'src', self::$service->parameterToAbsolutePath('src'));
        $this->assertSame(self::$projectDir_orig.\DIRECTORY_SEPARATOR.'src', self::$service->parameterToAbsolutePath('./src'));

        //Invalid pathes should return null
        $this->assertNull(self::$service->parameterToAbsolutePath('/this/path/does/not/exist'));
        $this->assertNull(self::$service->parameterToAbsolutePath('/./this/one/too'));
    }

    public function placeholderDataProvider()
    {
        return [
            ['%FOOTPRINTS%/test/test.jpg', self::$footprint_path.'/test/test.jpg'],
            ['%FOOTPRINTS%/test/', self::$footprint_path.'/test/'],
            ['%MEDIA%/test', self::$media_path.'/test'],
            ['%MEDIA%', self::$media_path],
            ['%FOOTPRINTS%', self::$footprint_path],
            //Footprints 3D are disabled
            ['%FOOTPRINTS_3D%', null],
            //Check that invalid pathes return null
            ['/no/placeholder', null],
            ['%INVALID_PLACEHOLDER%', null],
            ['%FOOTPRINTS/test/', null], //Malformed placeholder
            ['/wrong/%FOOTRPINTS%/', null], //Placeholder not at beginning
            ['%FOOTPRINTS%/%MEDIA%', null], //No more than one placholder
            ['%FOOTPRINTS%/%FOOTPRINTS%', null],
            ['%FOOTPRINTS%/../../etc/passwd', null],
            ['%FOOTPRINTS%/0\..\test', null],
        ];
    }

    public function realPathDataProvider()
    {
        return [
            [self::$media_path.'/test/img.jpg', '%MEDIA%/test/img.jpg'],
            [self::$media_path.'/test/img.jpg', '%BASE%/data/media/test/img.jpg', true],
            [self::$footprint_path.'/foo.jpg', '%FOOTPRINTS%/foo.jpg'],
            [self::$footprint_path.'/foo.jpg', '%FOOTPRINTS%/foo.jpg', true],
            //Every kind of absolute path, that is not based with our placeholder dirs must be invald
            ['/etc/passwd', null],
            ['C:\\not\\existing.txt', null],
            //More then one placeholder is not allowed
            [self::$footprint_path.'/test/'.self::$footprint_path, null],
            //Path must begin with path
            ['/not/root'.self::$footprint_path, null],
        ];
    }

    /**
     * @dataProvider placeholderDataProvider
     */
    public function testPlaceholderToRealPath($param, $expected): void
    {
        $this->assertSame($expected, self::$service->placeholderToRealPath($param));
    }

    /**
     * @dataProvider realPathDataProvider
     */
    public function testRealPathToPlaceholder($param, $expected, $old_method = false): void
    {
        $this->assertSame($expected, self::$service->realPathToPlaceholder($param, $old_method));
    }
}
