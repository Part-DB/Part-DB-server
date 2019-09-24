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

namespace App\Tests\Entity;


use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\PartAttachment;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class AttachmentTest extends TestCase
{
    public function testIsExternal()
    {
        $attachment = new PartAttachment();
        $this->setProtectedProperty($attachment, 'path', '%MEDIA%/foo/bar.txt');
        $this->assertFalse($attachment->isExternal());

        $this->setProtectedProperty($attachment, 'path', '%BASE%/foo/bar.jpg');
        $this->assertFalse($attachment->isExternal());

        $this->setProtectedProperty($attachment, 'path', '%FOOTPRINTS%/foo/bar.jpg');
        $this->assertFalse($attachment->isExternal());

        $this->setProtectedProperty($attachment, 'path', '%FOOTPRINTS3D%/foo/bar.jpg');
        $this->assertFalse($attachment->isExternal());

        //Every other string is not a external attachment
        $this->setProtectedProperty($attachment, 'path', '%test%/foo/bar.ghp');
        $this->assertTrue($attachment->isExternal());

        $this->setProtectedProperty($attachment, 'path', 'foo%MEDIA%/foo.jpg');
        $this->assertTrue($attachment->isExternal());

        $this->setProtectedProperty($attachment, 'path', 'foo%MEDIA%/%BASE%foo.jpg');
        $this->assertTrue($attachment->isExternal());
    }

    public function testGetExtension()
    {
        $attachment = new PartAttachment();
        $this->setProtectedProperty($attachment, 'path', '%MEDIA%/foo/bar.txt');
        $this->assertEquals('txt', $attachment->getExtension());

        $this->setProtectedProperty($attachment, 'path', '%MEDIA%/foo/bar.JPeg');
        $this->assertEquals('jpeg', $attachment->getExtension());

        //Test if we can override the filename
        $this->setProtectedProperty($attachment, 'path', '%MEDIA%/foo/bar.JPeg');
        $this->setProtectedProperty($attachment, 'original_filename', 'test.txt');
        $this->assertEquals('txt', $attachment->getExtension());

        $this->setProtectedProperty($attachment, 'path', 'https://foo.bar');
        $this->assertNull( $attachment->getExtension());

        $this->setProtectedProperty($attachment, 'path', 'https://foo.bar/test.jpeg');
        $this->assertNull( $attachment->getExtension());
    }

    public function testIsPicture()
    {
        $attachment = new PartAttachment();
        $this->setProtectedProperty($attachment, 'path', '%MEDIA%/foo/bar.txt');
        $this->assertFalse($attachment->isPicture());

        $this->setProtectedProperty($attachment, 'path', 'https://test.de/picture.jpeg');
        $this->assertTrue($attachment->isPicture());

        $this->setProtectedProperty($attachment, 'path', 'https://test.de');
        $this->assertTrue($attachment->isPicture());

        $this->setProtectedProperty($attachment, 'path', '%MEDIA%/foo/bar.jpeg');
        $this->assertTrue($attachment->isPicture());

        $this->setProtectedProperty($attachment, 'path', '%MEDIA%/foo/bar.webp');
        $this->assertTrue($attachment->isPicture());
    }

    public function testGetHost()
    {
        $attachment = new PartAttachment();
        $this->setProtectedProperty($attachment, 'path', '%MEDIA%/foo/bar.txt');
        $this->assertNull($attachment->getHost());

        $this->setProtectedProperty($attachment, 'path', 'https://www.google.de/test.txt');
        $this->assertEquals('www.google.de', $attachment->getHost());
    }

    public function testGetFilename()
    {
        $attachment = new PartAttachment();
        $this->setProtectedProperty($attachment, 'path', '%MEDIA%/foo/bar.txt');
        $this->assertEquals('bar.txt', $attachment->getFilename());

        $this->setProtectedProperty($attachment, 'path', '%MEDIA%/foo/bar.JPeg');
        $this->setProtectedProperty($attachment, 'original_filename', 'test.txt');
        $this->assertEquals('test.txt', $attachment->getFilename());

        $this->setProtectedProperty($attachment, 'path', 'https://www.google.de/test.txt');
        $this->assertNull($attachment->getFilename());
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