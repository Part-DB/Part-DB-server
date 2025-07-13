<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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
namespace App\Tests\Services\LogSystem;

use App\Entity\LogSystem\ElementEditedLogEntry;
use App\Entity\Parts\Category;
use App\Services\LogSystem\TimeTravel;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TimeTravelTest extends KernelTestCase
{

    private TimeTravel $service;
    private EntityManagerInterface $em;

    public function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(TimeTravel::class);
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testUndeleteEntity(): void
    {
        $undeletedCategory = $this->service->undeleteEntity(Category::class, 100);

        $this->assertInstanceOf(Category::class, $undeletedCategory);
        $this->assertEquals(100, $undeletedCategory->getId());
    }

    public function testApplyEntry(): void
    {
        $category = new Category();
        //Fake an ID
        $reflClass = new \ReflectionClass($category);
        $reflClass->getProperty('id')->setValue($category, 1000);

        $category->setName('Test Category');
        $category->setComment('Test Comment');

        $logEntry = new ElementEditedLogEntry($category);
        $logEntry->setOldData(['name' => 'Old Category', 'comment' => 'Old Comment']);

        $this->service->applyEntry($category, $logEntry);

        $this->assertEquals('Old Category', $category->getName());
        $this->assertEquals('Old Comment', $category->getComment());
    }

    public function testRevertEntityToTimestamp(): void
    {
        /** @var Category $category */
        $category = $this->em->find(Category::class, 1);

        $this->service->revertEntityToTimestamp($category, new \DateTime('2022-01-01 00:00:00'));

        //The category with 1 should have the name 'Test' at this timestamp
        $this->assertEquals('Test', $category->getName());
    }
}
