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

namespace App\Tests\Services\ImportExportSystem;

use App\Entity\Attachments\AttachmentType;
use App\Entity\UserSystem\User;
use App\Services\Formatters\AmountFormatter;
use App\Services\ImportExportSystem\EntityImporter;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * @group DB
 */
class EntityImporterTest extends WebTestCase
{
    /**
     * @var AmountFormatter
     */
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();

        //Get an service instance.
        self::bootKernel();
        $this->service = self::getContainer()->get(EntityImporter::class);
    }

    public function testMassCreationResults(): void
    {
        $errors = [];
        $results = $this->service->massCreation('', AttachmentType::class, null, $errors);
        $this->assertEmpty($results);
        $this->assertEmpty($errors);

        $errors = [];
        $lines = "Test 1\nTest 2   \nTest 3";
        $results = $this->service->massCreation($lines, AttachmentType::class, null, $errors);
        $this->assertCount(0, $errors);
        $this->assertCount(3, $results);
        //Check type
        $this->assertInstanceOf(AttachmentType::class, $results[0]);
        //Check names
        $this->assertSame('Test 1', $results[0]->getName());
        $this->assertSame('Test 2', $results[1]->getName());
        //Check parent
        $this->assertNull($results[0]->getMasterPictureAttachment());

        $parent = new AttachmentType();
        $results = $this->service->massCreation($lines, AttachmentType::class, $parent, $errors);
        $this->assertCount(3, $results);
        $this->assertSame($parent, $results[0]->getParent());
    }

    public function testNonStructuralClass(): void
    {
        $input = <<<EOT
Test1
   Test1.1
Test2
EOT;

        $errors = [];
        $results = $this->service->massCreation($input, User::class, null, $errors);

        //Import must not fail, even with non-structural classes
        $this->assertCount(3, $results);
        $this->assertCount(0, $errors);

        $this->assertSame('Test1', $results[0]->getName());
        $this->assertSame('Test1.1', $results[1]->getName());
        $this->assertSame('Test2', $results[2]->getName());

    }

    public function testMassCreationNested(): void
    {
        $input = <<<EOT
Test 1
   Test 1.1
    Test 1.1.1
    Test 1.1.2
   Test 1.2
      Test 1.2.1
Test 2
EOT;

        $errors = [];
        $parent = new AttachmentType();
        $results = $this->service->massCreation($input, AttachmentType::class, $parent, $errors);

        //We have 7 elements, an now errros
        $this->assertCount(0, $errors);
        $this->assertCount(7, $results);

        $element1 = $results[0];
        $element11 = $results[1];
        $element111 = $results[2];
        $element112 = $results[3];
        $element12 = $results[4];
        $element121 = $results[5];
        $element2 = $results[6];

        $this->assertSame('Test 1', $element1->getName());
        $this->assertSame('Test 1.1', $element11->getName());
        $this->assertSame('Test 1.1.1', $element111->getName());
        $this->assertSame('Test 1.1.2', $element112->getName());
        $this->assertSame('Test 1.2', $element12->getName());
        $this->assertSame('Test 1.2.1', $element121->getName());
        $this->assertSame('Test 2', $element2->getName());

        //Check parents
        $this->assertSame($parent, $element1->getParent());
        $this->assertSame($element1, $element11->getParent());
        $this->assertSame($element11, $element111->getParent());
        $this->assertSame($element11, $element112->getParent());
        $this->assertSame($element1, $element12->getParent());
        $this->assertSame($element12, $element121->getParent());
        $this->assertSame($parent, $element2->getParent());
    }

    public function testMassCreationErrors(): void
    {
        $errors = [];
        //Node 1 and Node 2 are created in datafixtures, so their attemp to create them again must fail.
        $lines = "Test 1\nNode 1\nNode 2";
        $results = $this->service->massCreation($lines, AttachmentType::class, null, $errors);
        $this->assertCount(1, $results);
        $this->assertSame('Test 1', $results[0]->getName());
        $this->assertCount(2, $errors);
        $this->assertSame('Node 1', $errors[0]['entity']->getName());
    }
}
