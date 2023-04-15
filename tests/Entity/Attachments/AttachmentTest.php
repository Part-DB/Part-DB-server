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

namespace App\Tests\Entity\Attachments;

use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\AttachmentType;
use App\Entity\Attachments\AttachmentTypeAttachment;
use App\Entity\Attachments\CategoryAttachment;
use App\Entity\Attachments\CurrencyAttachment;
use App\Entity\Attachments\ProjectAttachment;
use App\Entity\Attachments\FootprintAttachment;
use App\Entity\Attachments\GroupAttachment;
use App\Entity\Attachments\ManufacturerAttachment;
use App\Entity\Attachments\MeasurementUnitAttachment;
use App\Entity\Attachments\PartAttachment;
use App\Entity\Attachments\StorelocationAttachment;
use App\Entity\Attachments\SupplierAttachment;
use App\Entity\Attachments\UserAttachment;
use App\Entity\ProjectSystem\Project;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Part;
use App\Entity\Parts\Storelocation;
use App\Entity\Parts\Supplier;
use App\Entity\PriceInformations\Currency;
use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\User;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class AttachmentTest extends TestCase
{
    public function testEmptyState(): void
    {
        $attachment = new PartAttachment();

        $this->assertNull($attachment->getAttachmentType());
        $this->assertFalse($attachment->isPicture());
        $this->assertFalse($attachment->isExternal());
        $this->assertFalse($attachment->isSecure());
        $this->assertFalse($attachment->isBuiltIn());
        $this->assertFalse($attachment->is3DModel());
        $this->assertFalse($attachment->getShowInTable());
        $this->assertEmpty($attachment->getPath());
        $this->assertEmpty($attachment->getName());
        $this->assertEmpty($attachment->getURL());
        $this->assertEmpty($attachment->getExtension());
        $this->assertNull($attachment->getElement());
        $this->assertEmpty($attachment->getFilename());
    }

    public function subClassesDataProvider(): array
    {
        return [
            [AttachmentTypeAttachment::class, AttachmentType::class],
            [CategoryAttachment::class, Category::class],
            [CurrencyAttachment::class, Currency::class],
            [ProjectAttachment::class, Project::class],
            [FootprintAttachment::class, Footprint::class],
            [GroupAttachment::class, Group::class],
            [ManufacturerAttachment::class, Manufacturer::class],
            [MeasurementUnitAttachment::class, MeasurementUnit::class],
            [PartAttachment::class, Part::class],
            [StorelocationAttachment::class, Storelocation::class],
            [SupplierAttachment::class, Supplier::class],
            [UserAttachment::class, User::class],
        ];
    }

    /**
     * @dataProvider subClassesDataProvider
     */
    public function testSetElement(string $attachment_class, string $allowed_class): void
    {
        /** @var Attachment $attachment */
        $attachment = new $attachment_class();
        $element = new $allowed_class();

        //This must not throw an exception
        $attachment->setElement($element);
        $this->assertSame($element, $attachment->getElement());
    }

    /**
     * Test that all attachment subclasses like PartAttachment or similar returns an exception, when an not allowed
     * element is passed.
     *
     * @dataProvider subClassesDataProvider
     * @depends  testSetElement
     */
    public function testSetElementExceptionOnSubClasses(string $attachment_class, string $allowed_class): void
    {
        $this->expectException(InvalidArgumentException::class);

        /** @var Attachment $attachment */
        $attachment = new $attachment_class();
        if (Project::class !== $allowed_class) {
            $element = new Project();
        } else {
            $element = new Category();
        }
        $attachment->setElement($element);
    }

    public function externalDataProvider(): array
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
            ['foo%MEDIA%/%BASE%foo.jpg', true],
        ];
    }

    /**
     * @dataProvider externalDataProvider
     */
    public function testIsExternal($path, $expected): void
    {
        $attachment = new PartAttachment();
        $this->setProtectedProperty($attachment, 'path', $path);
        $this->assertSame($expected, $attachment->isExternal());
    }

    public function extensionDataProvider(): array
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
    public function testGetExtension($path, $originalFilename, $expected): void
    {
        $attachment = new PartAttachment();
        $this->setProtectedProperty($attachment, 'path', $path);
        $this->setProtectedProperty($attachment, 'original_filename', $originalFilename);
        $this->assertSame($expected, $attachment->getExtension());
    }

    public function pictureDataProvider(): array
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
    public function testIsPicture($path, $expected): void
    {
        $attachment = new PartAttachment();
        $this->setProtectedProperty($attachment, 'path', $path);
        $this->assertSame($expected, $attachment->isPicture());
    }

    public function builtinDataProvider(): array
    {
        return [
            ['', false],
            ['%MEDIA%/foo/bar.txt', false],
            ['%BASE%/foo/bar.txt', false],
            ['/', false],
            ['https://google.de', false],
            ['%FOOTPRINTS%/foo/bar.txt', true],
        ];
    }

    /**
     * @dataProvider builtinDataProvider
     */
    public function testIsBuiltIn($path, $expected): void
    {
        $attachment = new PartAttachment();
        $this->setProtectedProperty($attachment, 'path', $path);
        $this->assertSame($expected, $attachment->isBuiltIn());
    }

    public function hostDataProvider(): array
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
    public function testGetHost($path, $expected): void
    {
        $attachment = new PartAttachment();
        $this->setProtectedProperty($attachment, 'path', $path);
        $this->assertSame($expected, $attachment->getHost());
    }

    public function filenameProvider(): array
    {
        return [
            ['%MEDIA%/foo/bar.txt', null, 'bar.txt'],
            ['%MEDIA%/foo/bar.JPeg', 'test.txt', 'test.txt'],
            ['https://www.google.de/test.txt', null, null],
        ];
    }

    /**
     * @dataProvider filenameProvider
     */
    public function testGetFilename($path, $original_filename, $expected): void
    {
        $attachment = new PartAttachment();
        $this->setProtectedProperty($attachment, 'path', $path);
        $this->setProtectedProperty($attachment, 'original_filename', $original_filename);
        $this->assertSame($expected, $attachment->getFilename());
    }

    public function testIsURL(): void
    {
        $url = '%MEDIA%/test.txt';
        $this->assertFalse(Attachment::isValidURL($url));

        $url = 'https://google.de';
        $this->assertFalse(Attachment::isValidURL($url));

        $url = 'ftp://google.de';
        $this->assertTrue(Attachment::isValidURL($url, false, false));
        $this->assertFalse(Attachment::isValidURL($url, false, true));
    }

    /**
     * Sets a protected property on a given object via reflection.
     *
     * @param object $object   - instance in which protected value is being modified
     * @param string $property - property on instance being modified
     * @param mixed  $value    - new value of the property being modified
     */
    public function setProtectedProperty(object $object, string $property, $value): void
    {
        $reflection = new ReflectionClass($object);
        $reflection_property = $reflection->getProperty($property);
        $reflection_property->setAccessible(true);
        $reflection_property->setValue($object, $value);
    }
}
