<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2025 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Tests\Services;

use App\Entity\Parameters\CategoryParameter;
use App\Entity\Parts\Category;
use App\Exceptions\EntityNotSupportedException;
use App\Services\ElementTypes;
use PHPUnit\Framework\TestCase;

class ElementTypesTest extends TestCase
{

    public function testFromClass(): void
    {
        $this->assertSame(ElementTypes::CATEGORY, ElementTypes::fromClass(Category::class));
        $this->assertSame(ElementTypes::CATEGORY, ElementTypes::fromClass(new Category()));

        //Should also work with subclasses
        $this->assertSame(ElementTypes::PARAMETER, ElementTypes::fromClass(CategoryParameter::class));
        $this->assertSame(ElementTypes::PARAMETER, ElementTypes::fromClass(new CategoryParameter()));
    }

    public function testFromClassNotExisting(): void
    {
        $this->expectException(EntityNotSupportedException::class);
        ElementTypes::fromClass(\LogicException::class);
    }

    public function testFromValue(): void
    {
        //By enum value
        $this->assertSame(ElementTypes::CATEGORY, ElementTypes::fromValue('category'));
        $this->assertSame(ElementTypes::ATTACHMENT, ElementTypes::fromValue('attachment'));

        //From enum instance
        $this->assertSame(ElementTypes::CATEGORY, ElementTypes::fromValue(ElementTypes::CATEGORY));

        //From class string
        $this->assertSame(ElementTypes::CATEGORY, ElementTypes::fromValue(Category::class));
        $this->assertSame(ElementTypes::PARAMETER, ElementTypes::fromValue(CategoryParameter::class));

        //From class instance
        $this->assertSame(ElementTypes::CATEGORY, ElementTypes::fromValue(new Category()));
        $this->assertSame(ElementTypes::PARAMETER, ElementTypes::fromValue(new CategoryParameter()));
    }

    public function testGetDefaultLabelKey(): void
    {
        $this->assertSame('category.label', ElementTypes::CATEGORY->getDefaultLabelKey());
        $this->assertSame('attachment.label', ElementTypes::ATTACHMENT->getDefaultLabelKey());
    }

    public function testGetDefaultPluralLabelKey(): void
    {
        $this->assertSame('category.labelp', ElementTypes::CATEGORY->getDefaultPluralLabelKey());
        $this->assertSame('attachment.labelp', ElementTypes::ATTACHMENT->getDefaultPluralLabelKey());
    }


}
