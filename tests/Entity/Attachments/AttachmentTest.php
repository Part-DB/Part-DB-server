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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
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
use App\Entity\Attachments\StorageLocationAttachment;
use App\Entity\Attachments\SupplierAttachment;
use App\Entity\Attachments\UserAttachment;
use App\Entity\ProjectSystem\Project;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Part;
use App\Entity\Parts\StorageLocation;
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
        $this->assertFalse($attachment->hasExternal());
        $this->assertFalse($attachment->hasInternal());
        $this->assertFalse($attachment->isSecure());
        $this->assertFalse($attachment->isBuiltIn());
        $this->assertFalse($attachment->is3DModel());
        $this->assertFalse($attachment->getShowInTable());
        $this->assertEmpty($attachment->getInternalPath());
        $this->assertEmpty($attachment->getExternalPath());
        $this->assertEmpty($attachment->getName());
        $this->assertEmpty($attachment->getExtension());
        $this->assertNull($attachment->getElement());
        $this->assertEmpty($attachment->getFilename());
    }

    public function subClassesDataProvider(): \Iterator
    {
        yield [AttachmentTypeAttachment::class, AttachmentType::class];
        yield [CategoryAttachment::class, Category::class];
        yield [CurrencyAttachment::class, Currency::class];
        yield [ProjectAttachment::class, Project::class];
        yield [FootprintAttachment::class, Footprint::class];
        yield [GroupAttachment::class, Group::class];
        yield [ManufacturerAttachment::class, Manufacturer::class];
        yield [MeasurementUnitAttachment::class, MeasurementUnit::class];
        yield [PartAttachment::class, Part::class];
        yield [StorageLocationAttachment::class, StorageLocation::class];
        yield [SupplierAttachment::class, Supplier::class];
        yield [UserAttachment::class, User::class];
    }

    #[DataProvider('subClassesDataProvider')]
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
     * Test that all attachment subclasses like PartAttachment or similar returns an exception, when a not allowed
     * element is passed.
     */
    #[Depends('testSetElement')]
    #[DataProvider('subClassesDataProvider')]
    public function testSetElementExceptionOnSubClasses(string $attachment_class, string $allowed_class): void
    {
        $this->expectException(InvalidArgumentException::class);

        /** @var Attachment $attachment */
        $attachment = new $attachment_class();
        $element = Project::class !== $allowed_class ? new Project() : new Category();
        $attachment->setElement($element);
    }


    public static function extensionDataProvider(): \Iterator
    {
        yield ['%MEDIA%/foo/bar.txt',   'http://google.de',           null,        'txt'];
        yield ['%MEDIA%/foo/bar.JPeg',  'https://foo.bar',            null,        'jpeg'];
        yield ['%MEDIA%/foo/bar.JPeg',  null,                           'test.txt',  'txt'];
        yield ['%MEDIA%/foo/bar',       'https://foo.bar/test.jpeg',  null,        ''];
        yield ['%MEDIA%/foo.bar',       'test.txt',                   'bar',       ''];
        yield [null,                      'http://google.de',           null,        null];
        yield [null,                      'https://foo.bar',            null,        null];
        yield [null,                      ',https://foo.bar/test.jpeg', null,        null];
        yield [null,                      'test',                       null,        null];
        yield [null,                      'test.txt',                   null,        null];
    }

    #[DataProvider('extensionDataProvider')]
    public function testGetExtension(?string $internal_path, ?string $external_path, ?string $originalFilename, ?string $expected): void
    {
        $attachment = new PartAttachment();
        $this->setProtectedProperty($attachment, 'internal_path', $internal_path);
        $this->setProtectedProperty($attachment, 'external_path', $external_path);
        $this->setProtectedProperty($attachment, 'original_filename', $originalFilename);
        $this->assertSame($expected, $attachment->getExtension());
    }

    public static function pictureDataProvider(): \Iterator
    {
        yield [null,                        '%MEDIA%/foo/bar.txt',                               false];
        yield [null,                        'https://test.de/picture.jpeg',                      true];
        yield [null,                        'https://test.de/picture.png?test=fdsj&width=34',    true];
        yield [null,                        'https://invalid.invalid/file.txt',                  false];
        yield [null,                        'http://infsf.inda/file.zip?test',                   false];
        yield [null,                        'https://test.de',                                   true];
        yield [null,                        'https://invalid.com/invalid/pic',                   true];
        yield ['%MEDIA%/foo/bar.jpeg',    'https://invalid.invalid/file.txt',                  true];
        yield ['%MEDIA%/foo/bar.webp',    '',                                                  true];
        yield ['%MEDIA%/foo',             '',                                                  false];
        yield ['%SECURE%/foo.txt/test',   'https://test.de/picture.jpeg',                      false];
    }

    #[DataProvider('pictureDataProvider')]
    public function testIsPicture(?string $internal_path, ?string $external_path, bool $expected): void
    {
        $attachment = new PartAttachment();
        $this->setProtectedProperty($attachment, 'internal_path', $internal_path);
        $this->setProtectedProperty($attachment, 'external_path', $external_path);
        $this->assertSame($expected, $attachment->isPicture());
    }

    public static function builtinDataProvider(): \Iterator
    {
        yield ['', false];
        yield [null, false];
        yield ['%MEDIA%/foo/bar.txt', false];
        yield ['%BASE%/foo/bar.txt', false];
        yield ['/', false];
        yield ['https://google.de', false];
        yield ['%FOOTPRINTS%/foo/bar.txt', true];
    }

    #[DataProvider('builtinDataProvider')]
    public function testIsBuiltIn(?string $path, $expected): void
    {
        $attachment = new PartAttachment();
        $this->setProtectedProperty($attachment, 'internal_path', $path);
        $this->assertSame($expected, $attachment->isBuiltIn());
    }

    public static function hostDataProvider(): \Iterator
    {
        yield ['%MEDIA%/foo/bar.txt', null];
        yield ['https://www.google.de/test.txt', 'www.google.de'];
        yield ['https://foo.bar/test?txt=test', 'foo.bar'];
    }

    #[DataProvider('hostDataProvider')]
    public function testGetHost(?string $path, ?string $expected): void
    {
        $attachment = new PartAttachment();
        $this->setProtectedProperty($attachment, 'external_path', $path);
        $this->assertSame($expected, $attachment->getHost());
    }

    public static function filenameProvider(): \Iterator
    {
        yield ['%MEDIA%/foo/bar.txt',  'https://www.google.de/test.txt',   null,      'bar.txt'];
        yield ['%MEDIA%/foo/bar.JPeg', 'https://www.google.de/foo.txt',   'test.txt', 'test.txt'];
        yield ['',                     'https://www.google.de/test.txt',   null,       null];
        yield [null,                     'https://www.google.de/test.txt',   null,       null];
    }

    #[DataProvider('filenameProvider')]
    public function testGetFilename(?string $internal_path, ?string $external_path, ?string $original_filename, ?string $expected): void
    {
        $attachment = new PartAttachment();
        $this->setProtectedProperty($attachment, 'internal_path', $internal_path);
        $this->setProtectedProperty($attachment, 'external_path', $external_path);
        $this->setProtectedProperty($attachment, 'original_filename', $original_filename);
        $this->assertSame($expected, $attachment->getFilename());
    }

    public function testSetExternalPath(): void
    {
        $attachment = new PartAttachment();

        //Set URL
        $attachment->setExternalPath('https://google.de');
        $this->assertSame('https://google.de', $attachment->getExternalPath());

        //Ensure that changing the external path does reset the internal one
        $attachment->setInternalPath('%MEDIA%/foo/bar.txt');
        $attachment->setExternalPath('https://example.de');
        $this->assertSame(null, $attachment->getInternalPath());

        //Ensure that setting the same value to the external path again doesn't reset the internal one
        $attachment->setExternalPath('https://google.de');
        $attachment->setInternalPath('%MEDIA%/foo/bar.txt');
        $attachment->setExternalPath('https://google.de');
        $this->assertSame('%MEDIA%/foo/bar.txt', $attachment->getInternalPath());

        //Ensure that resetting the external path doesn't reset the internal one
        $attachment->setInternalPath('%MEDIA%/foo/bar.txt');
        $attachment->setExternalPath('');
        $this->assertSame('%MEDIA%/foo/bar.txt', $attachment->getInternalPath());

        //Ensure that spaces get replaced by %20
        $attachment->setExternalPath('https://example.de/test file.txt');
        $this->assertSame('https://example.de/test%20file.txt', $attachment->getExternalPath());
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
    public function setProtectedProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new ReflectionClass($object);
        $reflection_property = $reflection->getProperty($property);
        $reflection_property->setAccessible(true);
        $reflection_property->setValue($object, $value);
    }
}
