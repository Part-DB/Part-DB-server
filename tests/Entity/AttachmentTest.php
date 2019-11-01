<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony)
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
 *
 */

namespace App\Tests\Entity;


use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\PartAttachment;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class AttachmentTest extends TestCase
{
    public function externalDataProvider()
    {
        return [
            ['', false],
            ['%MEDIA%/foo/bar.txt', false],
            ['%BASE%/foo/bar.jpg', false],
            ['%FOOTPRINTS%/foo/bar.jpg', false],
            ['%FOOTPRINTS3D%/foo/bar.jpg', false],
            ['%SECURE%/test.txt', false],
            ['%test%/foo/bar.ghp', true],
            ['foo%MEDIA%/foo.jpg', true],
            ['foo%MEDIA%/%BASE%foo.jpg', true]
        ];
    }

    /**
     * @dataProvider externalDataProvider
     */
    public function testIsExternal($path, $expected)
    {
        $attachment = new PartAttachment();
        $this->setProtectedProperty($attachment, 'path', $path);
        $this->assertEquals($expected, $attachment->isExternal());
    }

    public function extensionDataProvider()
    {
        return [
            ['%MEDIA%/foo/bar.txt', null, 'txt'],
            ['%MEDIA%/foo/bar.JPeg', null, 'jpeg'],
            ['%MEDIA%/foo/bar.JPeg', 'test.txt', 'txt'],
            ['%MEDIA%/foo/bar', null, ''],
            ['%MEDIA%/foo.bar', 'bar', ''],
            ['http://google.de', null, null],
            ['https://foo.bar', null, null],
            ['https://foo.bar/test.jpeg', null, null],
            ['test', null, null],
            ['test.txt', null, null],
        ];
    }

    /**
     * @dataProvider extensionDataProvider
     */
    public function testGetExtension($path, $originalFilename, $expected)
    {
        $attachment = new PartAttachment();
        $this->setProtectedProperty($attachment, 'path', $path);
        $this->setProtectedProperty($attachment, 'original_filename', $originalFilename);
        $this->assertEquals($expected, $attachment->getExtension());
    }

    public function pictureDataProvider()
    {
        return [
            ['%MEDIA%/foo/bar.txt', false],
            ['https://test.de/picture.jpeg', true],
            ['https://test.de', true],
            ['http://test.de/google.de', true],
            ['%MEDIA%/foo/bar.jpeg', true],
            ['%MEDIA%/foo/bar.webp', true],
            ['%MEDIA%/foo', false],
            ['%SECURE%/foo.txt/test', false],
        ];
    }

    /**
     * @dataProvider pictureDataProvider
     */
    public function testIsPicture($path, $expected)
    {
        $attachment = new PartAttachment();
        $this->setProtectedProperty($attachment, 'path', $path);
        $this->assertEquals($expected, $attachment->isPicture());
    }

    public function builtinDataProvider()
    {
        return [
            ['', false],
            ['%MEDIA%/foo/bar.txt', false],
            ['%BASE%/foo/bar.txt', false],
            ['/', false],
            ['https://google.de', false],
            ['%FOOTPRINTS%/foo/bar.txt', true]
        ];
    }

    /**
     * @dataProvider builtinDataProvider
     */
    public function testIsBuiltIn($path, $expected)
    {
        $attachment = new PartAttachment();
        $this->setProtectedProperty($attachment, 'path', $path);
        $this->assertEquals($expected, $attachment->isBuiltIn());
    }

    public function hostDataProvider()
    {
        return [
            ['%MEDIA%/foo/bar.txt', null],
            ['https://www.google.de/test.txt', 'www.google.de'],
            ['https://foo.bar/test?txt=test', 'foo.bar'],
        ];
    }

    /**
     * @dataProvider hostDataProvider
     */
    public function testGetHost($path, $expected)
    {
        $attachment = new PartAttachment();
        $this->setProtectedProperty($attachment, 'path', $path);
        $this->assertEquals($expected, $attachment->getHost());
    }

    public function filenameProvider()
    {
        return [
            ['%MEDIA%/foo/bar.txt', null, 'bar.txt'],
            ['%MEDIA%/foo/bar.JPeg', 'test.txt', 'test.txt'],
            ['https://www.google.de/test.txt', null, null]
        ];
    }

    /**
     * @dataProvider filenameProvider
     */
    public function testGetFilename($path, $original_filename, $expected)
    {
        $attachment = new PartAttachment();
        $this->setProtectedProperty($attachment, 'path', $path);
        $this->setProtectedProperty($attachment, 'original_filename', $original_filename);
        $this->assertEquals($expected, $attachment->getFilename());
    }

    public function testIsURL()
    {
        $url = '%MEDIA%/test.txt';
        $this->assertFalse(Attachment::isURL($url));

        $url = 'https://google.de';
        $this->assertFalse(Attachment::isURL($url));

        $url = 'ftp://google.de';
        $this->assertTrue(Attachment::isURL($url, false, false));
        $this->assertFalse(Attachment::isURL($url, false, true));
    }

    /**
     * Sets a protected property on a given object via reflection
     *
     * @param object $object - instance in which protected value is being modified
     * @param string $property - property on instance being modified
     * @param mixed $value - new value of the property being modified
     *
     * @return void
     */
    public function setProtectedProperty($object, $property, $value)
    {
        $reflection = new ReflectionClass($object);
        $reflection_property = $reflection->getProperty($property);
        $reflection_property->setAccessible(true);
        $reflection_property->setValue($object, $value);
    }

}