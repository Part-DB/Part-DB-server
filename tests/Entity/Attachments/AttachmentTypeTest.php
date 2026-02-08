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

use App\Entity\Attachments\AttachmentType;
use App\Entity\Attachments\PartAttachment;
use App\Entity\Attachments\UserAttachment;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\TestCase;

class AttachmentTypeTest extends TestCase
{
    public function testEmptyState(): void
    {
        $attachment_type = new AttachmentType();
        $this->assertInstanceOf(Collection::class, $attachment_type->getAttachmentsForType());
        $this->assertEmpty($attachment_type->getFiletypeFilter());
    }

    public function testSetAllowedTargets(): void
    {
        $attachmentType = new AttachmentType();


        $this->expectException(\InvalidArgumentException::class);
        $attachmentType->setAllowedTargets(['target1', 'target2']);
    }

    public function testGetSetAllowedTargets(): void
    {
        $attachmentType = new AttachmentType();

        $attachmentType->setAllowedTargets([PartAttachment::class, UserAttachment::class]);
        $this->assertSame([PartAttachment::class, UserAttachment::class], $attachmentType->getAllowedTargets());
        //Caching should also work
        $this->assertSame([PartAttachment::class, UserAttachment::class], $attachmentType->getAllowedTargets());

        //Setting null should reset the allowed targets
        $attachmentType->setAllowedTargets(null);
        $this->assertNull($attachmentType->getAllowedTargets());
    }

    public function testIsAllowedForTarget(): void
    {
        $attachmentType = new AttachmentType();

        //By default, all targets should be allowed
        $this->assertTrue($attachmentType->isAllowedForTarget(PartAttachment::class));
        $this->assertTrue($attachmentType->isAllowedForTarget(UserAttachment::class));

        //Set specific allowed targets
        $attachmentType->setAllowedTargets([PartAttachment::class]);
        $this->assertTrue($attachmentType->isAllowedForTarget(PartAttachment::class));
        $this->assertFalse($attachmentType->isAllowedForTarget(UserAttachment::class));

        //Set both targets
        $attachmentType->setAllowedTargets([PartAttachment::class, UserAttachment::class]);
        $this->assertTrue($attachmentType->isAllowedForTarget(PartAttachment::class));
        $this->assertTrue($attachmentType->isAllowedForTarget(UserAttachment::class));

        //Reset allowed targets
        $attachmentType->setAllowedTargets(null);
        $this->assertTrue($attachmentType->isAllowedForTarget(PartAttachment::class));
        $this->assertTrue($attachmentType->isAllowedForTarget(UserAttachment::class));
    }
}
