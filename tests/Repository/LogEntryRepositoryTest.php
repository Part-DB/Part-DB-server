<?php
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

namespace App\Tests\Repository;

use App\Entity\LogSystem\AbstractLogEntry;
use App\Entity\LogSystem\ElementEditedLogEntry;
use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use App\Entity\UserSystem\User;
use App\Repository\LogEntryRepository;
use App\Tests\Entity\LogSystem\AbstractLogEntryTest;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class LogEntryRepositoryTest extends KernelTestCase
{

    private EntityManagerInterface $entityManager;
    private LogEntryRepository $repo;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')->getManager();

        $this->repo = $this->entityManager->getRepository(AbstractLogEntry::class);
    }

    public function testFindBy(): void
    {
        //The findBy method should be able the target criteria and split it into the needed criterias

        $part = $this->entityManager->find(Part::class, 3);
        $elements = $this->repo->findBy(['target' => $part]);

        //It should only contain one log entry, where the part was created.
        $this->assertCount(1, $elements);
    }

    public function testGetTargetElement(): void
    {
        $part = $this->entityManager->find(Part::class, 3);
        $logEntry = $this->repo->findBy(['target' => $part])[0];

        $element = $this->repo->getTargetElement($logEntry);
        //The target element, must be the part we searched for
        $this->assertSame($part, $element);
    }

    public function testGetLastEditingUser(): void
    {
        //We have a edit log entry for the category with ID 1
        $category = $this->entityManager->find(Category::class, 1);
        $adminUser = $this->entityManager->getRepository(User::class)->findOneBy(['name' => 'admin']);

        $user = $this->repo->getLastEditingUser($category);

        //The last editing user should be the admin user
        $this->assertSame($adminUser, $user);

        //For the category 2, the user must be null
        $category = $this->entityManager->find(Category::class, 2);
        $user = $this->repo->getLastEditingUser($category);
        $this->assertNull($user);
    }

    public function testGetCreatingUser(): void
    {
        //We have a edit log entry for the category with ID 1
        $category = $this->entityManager->find(Category::class, 1);
        $adminUser = $this->entityManager->getRepository(User::class)->findOneBy(['name' => 'admin']);

        $user = $this->repo->getCreatingUser($category);

        //The last editing user should be the admin user
        $this->assertSame($adminUser, $user);

        //For the category 2, the user must be null
        $category = $this->entityManager->find(Category::class, 2);
        $user = $this->repo->getCreatingUser($category);
        $this->assertNull($user);
    }

    public function testGetLogsOrderedByTimestamp(): void
    {
        $logs = $this->repo->getLogsOrderedByTimestamp('DESC', 2, 0);

        //We have 2 log entries
        $this->assertCount(2, $logs);

        //The first one must be newer than the second one
        $this->assertGreaterThanOrEqual($logs[0]->getTimestamp(), $logs[1]->getTimestamp());
    }

    public function testGetElementExistedAtTimestamp(): void
    {
        $part = $this->entityManager->find(Part::class, 3);

        //Assume that the part is existing now
        $this->assertTrue($this->repo->getElementExistedAtTimestamp($part, new \DateTimeImmutable()));

        //Assume that the part was not existing long time ago
        $this->assertFalse($this->repo->getElementExistedAtTimestamp($part, new \DateTimeImmutable('2000-01-01')));
    }

    public function testGetElementHistory(): void
    {
        $category = $this->entityManager->find(Category::class, 1);

        $history = $this->repo->getElementHistory($category);

        //We have 4 log entries for the category
        $this->assertCount(4, $history);
    }


    public function testGetTimetravelDataForElement(): void
    {
        $category = $this->entityManager->find(Category::class, 1);
        $data = $this->repo->getTimetravelDataForElement($category, new \DateTimeImmutable('2020-01-01'));

        //The data must contain only ElementChangedLogEntry
        $this->assertCount(2, $data);
        $this->assertInstanceOf(ElementEditedLogEntry::class, $data[0]);
        $this->assertInstanceOf(ElementEditedLogEntry::class, $data[1]);
    }


    public function testGetUndeleteDataForElement(): void
    {
        $undeleteData = $this->repo->getUndeleteDataForElement(Category::class, 100);

        //This must be the delete log entry we created in the fixtures
        $this->assertSame('Node 100', $undeleteData->getOldName());
    }
}
