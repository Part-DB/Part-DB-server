<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\DataFixtures;

use App\Entity\Attachments\AttachmentType;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\ProjectSystem\Project;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\StorageLocation;
use App\Entity\Parts\Supplier;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use InvalidArgumentException;

class DataStructureFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(protected EntityManagerInterface $em)
    {
    }

    /**
     * Load data fixtures with the passed EntityManager.
     */
    public function load(ObjectManager $manager): void
    {
        //Reset autoincrement
        $types = [AttachmentType::class, Project::class, Category::class, Footprint::class, Manufacturer::class,
            MeasurementUnit::class, StorageLocation::class, Supplier::class,];

        foreach ($types as $type) {
            $this->createNodesForClass($type, $manager);
        }

        $manager->flush();
    }

    /**
     * Creates a datafixture with serveral nodes for the given class.
     *
     * @param string        $class   The class for which the nodes should be generated (must be a StructuralDBElement child)
     * @param ObjectManager $manager The ObjectManager that should be used to persist the nodes
     */
    public function createNodesForClass(string $class, ObjectManager $manager): void
    {
        if (!new $class() instanceof AbstractStructuralDBElement) {
            throw new InvalidArgumentException('$class must be a StructuralDBElement!');
        }

        /** @var AbstractStructuralDBElement $node1 */
        $node1 = new $class();
        $node1->setName('Node 1');
        $this->addReference($class . '_1', $node1);

        /** @var AbstractStructuralDBElement $node2 */
        $node2 = new $class();
        $node2->setName('Node 2');
        $this->addReference($class . '_2', $node2);

        /** @var AbstractStructuralDBElement $node3 */
        $node3 = new $class();
        $node3->setName('Node 3');
        $this->addReference($class . '_3', $node3);

        $node1_1 = new $class();
        $node1_1->setName('Node 1.1');
        $node1_1->setParent($node1);
        $this->addReference($class . '_4', $node1_1);

        $node1_2 = new $class();
        $node1_2->setName('Node 1.2');
        $node1_2->setParent($node1);
        $this->addReference($class . '_5', $node1_2);

        $node2_1 = new $class();
        $node2_1->setName('Node 2.1');
        $node2_1->setParent($node2);
        $this->addReference($class . '_6', $node2_1);

        $node1_1_1 = new $class();
        $node1_1_1->setName('Node 1.1.1');
        $node1_1_1->setParent($node1_1);
        $this->addReference($class . '_7', $node1_1_1);

        $manager->persist($node1);
        $manager->persist($node2);
        $manager->persist($node3);
        $manager->persist($node1_1);
        $manager->persist($node1_2);
        $manager->persist($node2_1);
        $manager->persist($node1_1_1);
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class
        ];
    }
}
