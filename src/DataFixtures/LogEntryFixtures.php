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

declare(strict_types=1);


namespace App\DataFixtures;

use App\Entity\LogSystem\ElementCreatedLogEntry;
use App\Entity\LogSystem\ElementDeletedLogEntry;
use App\Entity\LogSystem\ElementEditedLogEntry;
use App\Entity\Parts\Category;
use App\Entity\UserSystem\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class LogEntryFixtures extends Fixture implements DependentFixtureInterface
{

    public function load(ObjectManager $manager)
    {
        $this->createCategoryEntries($manager);
        $this->createDeletedCategory($manager);
    }

    public function createCategoryEntries(ObjectManager $manager): void
    {
        $category = $this->getReference(Category::class . '_1', Category::class);

        $logEntry = new ElementCreatedLogEntry($category);
        $logEntry->setUser($this->getReference(UserFixtures::ADMIN, User::class));
        $logEntry->setComment('Test');
        $manager->persist($logEntry);

        $logEntry = new ElementEditedLogEntry($category);
        $logEntry->setUser($this->getReference(UserFixtures::ADMIN, User::class));
        $logEntry->setComment('Test');

        $logEntry->setOldData(['name' => 'Test']);
        $logEntry->setNewData(['name' => 'Node 1.1']);

        $manager->persist($logEntry);
        $manager->flush();
    }

    public function createDeletedCategory(ObjectManager $manager): void
    {
        //We create a fictive category to test the deletion
        $category = new Category();
        $category->setName('Node 100');

        //Assume a category with id 100 was deleted
        $reflClass = new \ReflectionClass($category);
        $reflClass->getProperty('id')->setValue($category, 100);

        //The whole lifecycle from creation to deletion
        $logEntry = new ElementCreatedLogEntry($category);
        $logEntry->setUser($this->getReference(UserFixtures::ADMIN, User::class));
        $logEntry->setComment('Creation');
        $manager->persist($logEntry);

        $logEntry = new ElementEditedLogEntry($category);
        $logEntry->setUser($this->getReference(UserFixtures::ADMIN, User::class));
        $logEntry->setComment('Edit');
        $logEntry->setOldData(['name' => 'Test']);
        $logEntry->setNewData(['name' => 'Node 100']);
        $manager->persist($logEntry);

        $logEntry = new ElementDeletedLogEntry($category);
        $logEntry->setUser($this->getReference(UserFixtures::ADMIN, User::class));
        $logEntry->setOldData(['name' => 'Node 100', 'id' => 100, 'comment' => 'Test comment']);
        $manager->persist($logEntry);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            DataStructureFixtures::class
        ];
    }
}