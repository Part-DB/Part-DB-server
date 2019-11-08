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

namespace App\DataFixtures;

use App\Entity\Attachments\AttachmentType;
use App\Entity\Base\StructuralDBElement;
use App\Entity\Devices\Device;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Storelocation;
use App\Entity\Parts\Supplier;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManagerInterface;

class DataStructureFixtures extends Fixture
{
    protected $em;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
    }

    /**
     * Load data fixtures with the passed EntityManager.
     */
    public function load(ObjectManager $manager)
    {
        //Reset autoincrement
        $types = [AttachmentType::class, Device::class, Category::class, Footprint::class, Manufacturer::class,
            MeasurementUnit::class, Storelocation::class, Supplier::class, ];

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
    public function createNodesForClass(string $class, ObjectManager $manager)
    {
        if (!new $class() instanceof StructuralDBElement) {
            throw new \InvalidArgumentException('$class must be a StructuralDBElement!');
        }

        $table_name = $this->em->getClassMetadata($class)->getTableName();
        $this->em->getConnection()->exec("ALTER TABLE `$table_name` AUTO_INCREMENT = 1;");

        /** @var StructuralDBElement $node1 */
        $node1 = new $class();
        $node1->setName('Node 1');

        /** @var StructuralDBElement $node2 */
        $node2 = new $class();
        $node2->setName('Node 2');

        /** @var StructuralDBElement $node3 */
        $node3 = new $class();
        $node3->setName('Node 3');

        $node1_1 = new $class();
        $node1_1->setName('Node 1.1');
        $node1_1->setParent($node1);

        $node1_2 = new $class();
        $node1_2->setName('Node 1.2');
        $node1_2->setParent($node1);

        $node2_1 = new $class();
        $node2_1->setName('Node 2.1');
        $node2_1->setParent($node2);

        $node1_1_1 = new $class();
        $node1_1_1->setName('Node 1.1.1');
        $node1_1_1->setParent($node1_1);

        $manager->persist($node1);
        $manager->persist($node2);
        $manager->persist($node3);
        $manager->persist($node1_1);
        $manager->persist($node1_2);
        $manager->persist($node2_1);
        $manager->persist($node1_1_1);
    }
}
