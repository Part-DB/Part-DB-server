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

use App\Entity\Parts\Category;
use App\Serializer\StructuralElementDenormalizer;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class StructuralElementDenormalizerTest extends WebTestCase
{

    /** @var StructuralElementDenormalizer */
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        //Get a service instance.
        self::bootKernel();
        $this->service = self::getContainer()->get(StructuralElementDenormalizer::class);
    }

    public function testHasCacheableSupportsMethod(): void
    {
        $this->assertFalse($this->service->hasCacheableSupportsMethod());
    }

    public function testSupportsDenormalization(): void
    {
        $this->assertFalse($this->service->supportsDenormalization('doesnt_matter', Category::class, 'json', ['groups' => ['import']]));
        $this->assertFalse($this->service->supportsDenormalization(['name' => 'Test'], Category::class, 'json', ['groups' => ['simple']]));

        //Denormalizer should only be active, when we use the import function
        $this->assertTrue($this->service->supportsDenormalization(['name' => 'Test'], Category::class, 'json', ['groups' => ['import']]));
    }

    /**
     * @group DB
     */
    public function testDenormalize(): void
    {
        //Check that we retrieve DB elements via the name
        $data = ['name' => 'Node 1'];
        $result = $this->service->denormalize($data, Category::class, 'json', ['groups' => ['import']]);
        $this->assertInstanceOf(Category::class, $result);
        $this->assertSame('Node 1', $result->getName());
        $this->assertNotNull($result->getID()); //ID should be set, because we retrieved the element from the DB

        //Check that we can retrieve nested DB elements
        $data = ['name' => 'Node 1.1', 'parent' => ['name' => 'Node 1']];
        $result = $this->service->denormalize($data, Category::class, 'json', ['groups' => ['import']]);
        $this->assertInstanceOf(Category::class, $result);
        $this->assertSame('Node 1.1', $result->getName());
        $this->assertSame('Node 1', $result->getParent()->getName());
        $this->assertNotNull($result->getID()); //ID should be set, because we retrieved the element from the DB
        $this->assertNotNull($result->getParent()->getID()); //ID should be set, because we retrieved the element from the DB

        //Check that we can create new elements
        $data = ['name' => 'New Node 1.1', 'parent' => ['name' => 'New Node 1']];
        $result = $this->service->denormalize($data, Category::class, 'json', ['groups' => ['import']]);
        $this->assertInstanceOf(Category::class, $result);
        $this->assertSame('New Node 1.1', $result->getName());
        $this->assertSame('New Node 1', $result->getParent()->getName());
        $this->assertNull($result->getID()); //ID should be null, because we created a new element

        //Check that when we retrieve this element again, we get the same instance
        $result2 = $this->service->denormalize($data, Category::class, 'json', ['groups' => ['import']]);
        $this->assertSame($result, $result2);
    }
}
