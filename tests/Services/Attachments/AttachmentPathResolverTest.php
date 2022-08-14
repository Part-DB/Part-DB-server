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
use const DIRECTORY_SEPARATOR;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AttachmentPathResolverTest extends WebTestCase
{
    protected $media_path;
    protected $footprint_path;
    protected $projectDir_orig;
    protected $projectDir;
    /**
     * @var AmountFormatter
     */
    protected $service;

    public function setUp(): void
    {
        parent::setUp();

        //Get an service instance.
        self::bootKernel();

        $this->projectDir_orig = realpath(self::$kernel->getProjectDir());
        $this->projectDir = str_replace('\\', '/', $this->projectDir_orig);
        $this->media_path = $this->projectDir.'/public/media';
        $this->footprint_path = $this->projectDir.'/public/img/footprints';

        $this->service = self::getContainer()->get(AttachmentPathResolver::class);
    }

    public function testParameterToAbsolutePath(): void
    {
        //If null is passed, null must be returned
        $this->assertNull($this->service->parameterToAbsolutePath(null));

        //Absolute path should be returned like they are (we use projectDir here, because we know that this dir exists)
        $this->assertSame($this->projectDir_orig, $this->service->parameterToAbsolutePath($this->projectDir));

        //Relative pathes should be resolved
        $expected = str_replace('\\', '/', $this->projectDir_orig.DIRECTORY_SEPARATOR.'src');
        $this->assertSame($expected, $this->service->parameterToAbsolutePath('src'));
        $this->assertSame($expected, $this->service->parameterToAbsolutePath('./src'));

        //Invalid pathes should return null
        $this->assertNull($this->service->parameterToAbsolutePath('/this/path/does/not/exist'));
        $this->assertNull($this->service->parameterToAbsolutePath('/./this/one/too'));
    }

    public function placeholderDataProvider(): array
    {
        //We need to do initialization (again), as dataprovider is called before setUp()
        self::bootKernel();
        $this->projectDir_orig = realpath(self::$kernel->getProjectDir());
        $this->projectDir = str_replace('\\', '/', $this->projectDir_orig);
        $this->media_path = $this->projectDir.'/public/media';
        $this->footprint_path = $this->projectDir.'/public/img/footprints';

        return [
            ['%FOOTPRINTS%/test/test.jpg', $this->footprint_path.'/test/test.jpg'],
            ['%FOOTPRINTS%/test/', $this->footprint_path.'/test/'],
            ['%MEDIA%/test', $this->media_path.'/test'],
            ['%MEDIA%', $this->media_path],
            ['%FOOTPRINTS%', $this->footprint_path],
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

    public function realPathDataProvider(): array
    {
        //We need to do initialization (again), as dataprovider is called before setUp()
        self::bootKernel();
        $this->projectDir_orig = realpath(self::$kernel->getProjectDir());
        $this->projectDir = str_replace('\\', '/', $this->projectDir_orig);
        $this->media_path = $this->projectDir.'/public/media';
        $this->footprint_path = $this->projectDir.'/public/img/footprints';

        return [
            [$this->media_path.'/test/img.jpg', '%MEDIA%/test/img.jpg'],
            [$this->media_path.'/test/img.jpg', '%BASE%/data/media/test/img.jpg', true],
            [$this->footprint_path.'/foo.jpg', '%FOOTPRINTS%/foo.jpg'],
            [$this->footprint_path.'/foo.jpg', '%FOOTPRINTS%/foo.jpg', true],
            //Every kind of absolute path, that is not based with our placeholder dirs must be invald
            ['/etc/passwd', null],
            ['C:\\not\\existing.txt', null],
            //More then one placeholder is not allowed
            [$this->footprint_path.'/test/'.$this->footprint_path, null],
            //Path must begin with path
            ['/not/root'.$this->footprint_path, null],
        ];
    }

    /**
     * @dataProvider placeholderDataProvider
     */
    public function testPlaceholderToRealPath($param, $expected): void
    {
        $this->assertSame($expected, $this->service->placeholderToRealPath($param));
    }

    /**
     * @dataProvider realPathDataProvider
     */
    public function testRealPathToPlaceholder($param, $expected, $old_method = false): void
    {
        $this->assertSame($expected, $this->service->realPathToPlaceholder($param, $old_method));
    }
}
