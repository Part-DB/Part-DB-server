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
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Part;
use App\Serializer\BigNumberNormalizer;
use App\Serializer\StructuralElementNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class StructuralElementNormalizerTest extends WebTestCase
{

    /** @var StructuralElementNormalizer */
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        //Get an service instance.
        self::bootKernel();
        $this->service = self::getContainer()->get(StructuralElementNormalizer::class);
    }

    public function testNormalize()
    {
        $category1 = (new Category())->setName('Category 1');
        $category11 = (new Category())->setName('Category 1.1');
        $category11->setParent($category1);

        //Serialize category 1
        $data1 = $this->service->normalize($category1, 'json', ['groups' => ['simple']]);
        $this->assertArrayHasKey('full_name', $data1);
        $this->assertSame('Category 1', $data1['full_name']);
        //Json export must contain type attribute
        $this->assertArrayHasKey('type', $data1);

        //Serialize category 1.1
        $data11 = $this->service->normalize($category11, 'json', ['groups' => ['simple']]);
        $this->assertArrayHasKey('full_name', $data11);
        $this->assertSame('Category 1->Category 1.1', $data11['full_name']);

        //Test that type attribute is removed for CSV export
        $data11 = $this->service->normalize($category11, 'csv', ['groups' => ['simple']]);
        $this->assertArrayNotHasKey('type', $data11);
    }

    public function testSupportsNormalization()
    {
        //Normalizer must only support StructuralElement objects (and child classes)
        $this->assertFalse($this->service->supportsNormalization(new \stdClass()));
        $this->assertFalse($this->service->supportsNormalization(new Part()));
        $this->assertTrue($this->service->supportsNormalization(new Category()));
        $this->assertTrue($this->service->supportsNormalization(new Footprint()));

    }
}
