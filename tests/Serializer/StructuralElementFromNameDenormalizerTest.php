<?php
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

namespace App\Tests\Serializer;

use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Parts\Category;
use App\Serializer\StructuralElementFromNameDenormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class StructuralElementFromNameDenormalizerTest extends WebTestCase
{

    /** @var StructuralElementFromNameDenormalizer */
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        //Get an service instance.
        self::bootKernel();
        $this->service = self::getContainer()->get(StructuralElementFromNameDenormalizer::class);
    }

    public function testSupportsDenormalization(): void
    {
        //Only the combination of string data and StructuralElement class as type is supported.
        $this->assertFalse($this->service->supportsDenormalization('doesnt_matter', \stdClass::class));
        $this->assertFalse($this->service->supportsDenormalization(['a' => 'b'], Category::class));

        $this->assertTrue($this->service->supportsDenormalization('doesnt_matter', Category::class));
    }

    public function testDenormalizeCreateNew(): void
    {
        $context = [
            'groups' => ['simple'],
            'path_delimiter' => '->',
            'create_unknown_datastructures' => true,
        ];

        //Test for simple category
        $category = $this->service->denormalize('New Category', Category::class, null, $context);
        $this->assertInstanceOf(Category::class, $category);
        $this->assertSame('New Category', $category->getName());

        //Test for nested category
        $category = $this->service->denormalize('New Category->Sub Category', Category::class, null, $context);
        $this->assertInstanceOf(Category::class, $category);
        $this->assertSame('Sub Category', $category->getName());
        $this->assertInstanceOf(Category::class, $category->getParent());
        $this->assertSame('New Category', $category->getParent()->getName());

        //Test with existing category
        $category = $this->service->denormalize('Node 1->Node 1.1', Category::class, null, $context);
        $this->assertInstanceOf(Category::class, $category);
        $this->assertSame('Node 1.1', $category->getName());
        $this->assertInstanceOf(Category::class, $category->getParent());
        $this->assertSame('Node 1', $category->getParent()->getName());
        //Both categories should be in DB (have an ID)
        $this->assertNotNull($category->getID());
        $this->assertNotNull($category->getParent()->getID());

        //Test with other path_delimiter
        $context['path_delimiter'] = '/';
        $category = $this->service->denormalize('New Category/Sub Category', Category::class, null, $context);
        $this->assertInstanceOf(Category::class, $category);
        $this->assertSame('Sub Category', $category->getName());
        $this->assertInstanceOf(Category::class, $category->getParent());
        $this->assertSame('New Category', $category->getParent()->getName());

        //Test with empty path
        $category = $this->service->denormalize('', Category::class, null, $context);
        $this->assertNull($category);
    }

    public function testDenormalizeOnlyExisting(): void
    {
        $context = [
            'groups' => ['simple'],
            'path_delimiter' => '->',
            'create_unknown_datastructures' => false,
        ];

        //Test with existing category
        $category = $this->service->denormalize('Node 1->Node 1.1', Category::class, null, $context);
        $this->assertInstanceOf(Category::class, $category);
        $this->assertSame('Node 1.1', $category->getName());
        $this->assertInstanceOf(Category::class, $category->getParent());
        $this->assertSame('Node 1', $category->getParent()->getName());
        //Both categories should be in DB (have an ID)
        $this->assertNotNull($category->getID());
        $this->assertNotNull($category->getParent()->getID());

        //Test with non existing category
        $category = $this->service->denormalize('New category', Category::class, null, $context);
        $this->assertNull($category);

        //Test with empty path
        $category = $this->service->denormalize('', Category::class, null, $context);
        $this->assertNull($category);
    }


}
