<?php
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

namespace App\Tests\Services;

use App\Entity\Attachments\AttachmentType;
use App\Services\AmountFormatter;
use App\Services\EntityImporter;
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

    public function setUp(): void
    {
        parent::setUp();

        //Get an service instance.
        self::bootKernel();
        $this->service = self::$container->get(EntityImporter::class);
    }

    public function testMassCreationResults()
    {
        $errors = [];
        $results = $this->service->massCreation('', AttachmentType::class, null, $errors);
        $this->assertEmpty($results);
        $this->assertEmpty($errors);

        $errors = [];
        $lines = "Test 1 \n Test 2 \n Test 3";
        $results = $this->service->massCreation($lines, AttachmentType::class, null, $errors);
        $this->assertCount(0, $errors);
        $this->assertCount(3, $results);
        //Check type
        $this->assertInstanceOf(AttachmentType::class, $results[0]);
        //Check names
        $this->assertEquals('Test 1', $results[0]->getName());
        $this->assertEquals('Test 2', $results[1]->getName());
        //Check parent
        $this->assertNull($results[0]->getMasterPictureAttachment());

        $parent = new AttachmentType();
        $results = $this->service->massCreation($lines, AttachmentType::class, $parent, $errors);
        $this->assertCount(3, $results);
        $this->assertEquals($parent, $results[0]->getParent());
    }

    public function testMassCreationErrors()
    {
        $errors = [];
        //Node 1 and Node 2 are created in datafixtures, so their attemp to create them again must fail.
        $lines = "Test 1 \n Node 1 \n Node 2";
        $results = $this->service->massCreation($lines, AttachmentType::class, null, $errors);
        $this->assertCount(1, $results);
        $this->assertEquals('Test 1', $results[0]->getName());
        $this->assertCount(2, $errors);
        $this->assertEquals('Node 1', $errors[0]['entity']->getName());
    }
}
