<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

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

namespace App\Tests\Entity\LogSystem;

use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\AttachmentType;
use App\Entity\Attachments\PartAttachment;
use App\Entity\LogSystem\UserLoginLogEntry;
use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Entity\LogSystem\AbstractLogEntry;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\Part;
use App\Entity\Parts\StorageLocation;
use App\Entity\Parts\Supplier;
use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\User;
use PHPUnit\Framework\TestCase;

class AbstractLogEntryTest extends TestCase
{
    public function testSetGetTarget(): void
    {
        $part = $this->createMock(Part::class);
        $part->method('getID')->willReturn(10);

        $log = new class() extends AbstractLogEntry {
        };
        $log->setTargetElement($part);

        $this->assertSame(Part::class, $log->getTargetClass());
        $this->assertSame(10, $log->getTargetID());

        $log->setTargetElement(null);
        $this->assertNull($log->getTargetClass());
        $this->assertNull($log->getTargetID());
    }

    public function testCLIUsername(): void
    {
        $log = new UserLoginLogEntry('1.1.1.1');

        //By default, no CLI username is set
        $this->assertNull($log->getCLIUsername());
        $this->assertFalse($log->isCLIEntry());

        $user = new User();
        $user->setName('test');
        $log->setUser($user);

        //Set a CLI username
        $log->setCLIUsername('root');
        $this->assertSame('root', $log->getCLIUsername());
        $this->assertTrue($log->isCLIEntry());

        //Normal user must be null now
        $this->assertNull($log->getUser());
    }
}
