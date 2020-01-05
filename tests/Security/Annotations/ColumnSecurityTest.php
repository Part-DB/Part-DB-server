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

namespace App\Tests\Security\Annotations;

use App\Entity\Attachments\AttachmentType;
use App\Security\Annotations\ColumnSecurity;
use PHPUnit\Framework\TestCase;

class ColumnSecurityTest extends TestCase
{
    public function testGetReadOperation(): void
    {
        $annotation = new ColumnSecurity();
        $this->assertSame('read', $annotation->getReadOperationName(), 'A new annotation must return read');
        $annotation->read = 'overwritten';
        $this->assertSame('overwritten', $annotation->getReadOperationName());
        $annotation->prefix = 'prefix';
        $this->assertSame('prefix.overwritten', $annotation->getReadOperationName());
    }

    public function testGetEditOperation(): void
    {
        $annotation = new ColumnSecurity();
        $this->assertSame('edit', $annotation->getEditOperationName(), 'A new annotation must return read');
        $annotation->edit = 'overwritten';
        $this->assertSame('overwritten', $annotation->getEditOperationName());
        $annotation->prefix = 'prefix';
        $this->assertSame('prefix.overwritten', $annotation->getEditOperationName());
    }

    public function placeholderScalarDataProvider(): array
    {
        return [
            ['string', '???'],
            ['integer', 0],
            ['int', 0],
            ['float', 0.0],
            ['object', null],
            ['bool', false],
            ['boolean', false],
            //['datetime', (new \DateTime())->setTimestamp(0)]
        ];
    }

    /**
     * @dataProvider placeholderScalarDataProvider
     *
     * @param $expected_value
     */
    public function testGetPlaceholderScalar(string $type, $expected_value): void
    {
        $annotation = new ColumnSecurity();
        $annotation->type = $type;
        $this->assertSame($expected_value, $annotation->getPlaceholder());
    }

    public function testGetPlaceholderSpecifiedValue(): void
    {
        $annotation = new ColumnSecurity();
        $annotation->placeholder = 3434;
        $this->assertSame(3434, $annotation->getPlaceholder());

        $annotation->placeholder = [323];
        $this->assertCount(1, $annotation->getPlaceholder());

        //If a placeholder is specified we allow every type
        $annotation->type = 'type2';
        $annotation->placeholder = 'invalid';
        $this->assertSame('invalid', $annotation->getPlaceholder());
    }

    public function testGetPlaceholderDBElement(): void
    {
        $annotation = new ColumnSecurity();
        $annotation->type = AttachmentType::class;

        /** @var AttachmentType $placeholder */
        $placeholder = $annotation->getPlaceholder();
        $this->assertInstanceOf(AttachmentType::class, $placeholder);
        $this->assertSame('???', $placeholder->getName());

        $annotation->placeholder = 'test';
        $placeholder = $annotation->getPlaceholder();
        $this->assertInstanceOf(AttachmentType::class, $placeholder);
        $this->assertSame('test', $placeholder->getName());
    }
}
