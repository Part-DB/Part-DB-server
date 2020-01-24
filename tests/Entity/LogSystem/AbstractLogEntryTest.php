<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Tests\Entity\LogSystem;

use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\AttachmentType;
use App\Entity\Attachments\PartAttachment;
use App\Entity\Devices\Device;
use App\Entity\LogSystem\AbstractLogEntry;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\Part;
use App\Entity\Parts\Storelocation;
use App\Entity\Parts\Supplier;
use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\User;
use PHPUnit\Framework\TestCase;
use App\Entity\Devices\DevicePart;

class AbstractLogEntryTest extends TestCase
{

    public function levelDataProvider(): array
    {
        return [
            [0, 'emergency'],
            [1, 'alert'],
            [2, 'critical'],
            [3, 'error'],
            [4, 'warning'],
            [5, 'notice'],
            [6, 'info'],
            [7, 'debug'],
            [8, 'blabla', true],
            [-1, 'test', true]
        ];
    }

    public function targetTypeDataProvider(): array
    {
        return [
            [1,  User::class],
            [2, Attachment::class],
            [3, AttachmentType::class],
            [4, Category::class],
            [5, Device::class],
            [6, DevicePart::class],
            [7, Footprint::class],
            [8, Group::class],
            [9, Manufacturer::class],
            [10, Part::class],
            [11, Storelocation::class],
            [12, Supplier::class],
            [-1, 'blablub', true]
        ];
    }

    /**
     * @dataProvider levelDataProvider
     */
    public function testLevelIntToString(int $int, string $expected_string, bool $expect_exception = false)
    {
        if ($expect_exception) {
            $this->expectException(\InvalidArgumentException::class);
        }
        $this->assertSame($expected_string, AbstractLogEntry::levelIntToString($int));
    }

    /**
     * @dataProvider levelDataProvider
     */
    public function testLevelStringToInt(int $expected_int, string $string, bool $expect_exception = false)
    {
        if ($expect_exception) {
            $this->expectException(\InvalidArgumentException::class);
        }
        $this->assertSame($expected_int, AbstractLogEntry::levelStringToInt($string));
    }

    /**
     * @dataProvider targetTypeDataProvider
     */
    public function testTargetTypeIdToClass(int $int, string $expected_class, bool $expect_exception = false)
    {
        if ($expect_exception) {
            $this->expectException(\InvalidArgumentException::class);
        }
        $this->assertSame($expected_class, AbstractLogEntry::targetTypeIdToClass($int));
    }

    /**
     * @dataProvider targetTypeDataProvider
     */
    public function testTypeClassToID(int $expected_id, string $class, bool $expect_exception = false)
    {
        if ($expect_exception) {
            $this->expectException(\InvalidArgumentException::class);
        }
        $this->assertSame($expected_id, AbstractLogEntry::targetTypeClassToID($class));
    }

    public function testTypeClassToIDSubclasses()
    {
        //Test if class mapping works for subclasses
        $this->assertSame(2, AbstractLogEntry::targetTypeClassToID(PartAttachment::class));
    }

    public function testSetGetTarget()
    {
        $part = $this->createMock(Part::class);
        $part->method('getID')->willReturn(10);

        $log = new class extends AbstractLogEntry {};
        $log->setTargetElement($part);

        $this->assertSame(Part::class, $log->getTargetClass());
        $this->assertSame(10, $log->getTargetID());

        $log->setTargetElement(null);
        $this->assertSame(null, $log->getTargetClass());
        $this->assertSame(null, $log->getTargetID());
    }
}
