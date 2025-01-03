<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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
namespace App\Tests\Entity\LogSystem;

use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\PartAttachment;
use App\Entity\LogSystem\LogTargetType;
use App\Entity\Parameters\PartParameter;
use App\Entity\Parts\Category;
use App\Entity\UserSystem\User;
use PHPUnit\Framework\TestCase;

class LogTargetTypeTest extends TestCase
{

    public function testToClass(): void
    {
        $this->assertNull(LogTargetType::NONE->toClass());
        $this->assertSame(User::class, LogTargetType::USER->toClass());
        $this->assertSame(Category::class, LogTargetType::CATEGORY->toClass());
        $this->assertSame(Attachment::class, LogTargetType::ATTACHMENT->toClass());
    }

    public function testFromElementClass(): void
    {
        //Test creation from string class
        $this->assertSame(LogTargetType::CATEGORY, LogTargetType::fromElementClass(Category::class));
        $this->assertSame(LogTargetType::USER, LogTargetType::fromElementClass(User::class));

        //Test creation from object
        $this->assertSame(LogTargetType::CATEGORY, LogTargetType::fromElementClass(new Category()));
        $this->assertSame(LogTargetType::USER, LogTargetType::fromElementClass(new User()));

        //Test creation from subclass
        $this->assertSame(LogTargetType::ATTACHMENT, LogTargetType::fromElementClass(new PartAttachment()));
        $this->assertSame(LogTargetType::PARAMETER, LogTargetType::fromElementClass(new PartParameter()));
    }

    public function testFromElementClassInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        LogTargetType::fromElementClass(new \stdClass());
    }
}
