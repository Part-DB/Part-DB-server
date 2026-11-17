<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Tests\Repository;

use App\Entity\Base\AbstractPartsContainingDBElement;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartCustomState;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\StorageLocation;
use App\Entity\Parts\Supplier;
use App\Entity\PriceInformations\Orderdetail;
use App\Repository\AbstractPartsContainingRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PartsContainingRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    public static function elementClasses(): iterable
    {
        foreach ([Category::class, Footprint::class, Manufacturer::class, MeasurementUnit::class,
                     PartCustomState::class, StorageLocation::class, Supplier::class] as $class) {
            yield $class => [$class];
        }
    }

    /**
     * @param  class-string<AbstractPartsContainingDBElement>  $class
     */
    #[DataProvider('elementClasses')]
    public function testRecursiveMethodsMatchTreeWalk(string $class): void
    {
        //Children are created out of name order, to check that they are walked ordered by name
        $root = (new $class())->setName('Recursive root');
        $b = (new $class())->setName('B')->setParent($root);
        $a = (new $class())->setName('A')->setParent($root);
        $a1 = (new $class())->setName('A1')->setParent($a);
        foreach ([$root, $b, $a, $a1] as $element) {
            $this->em->persist($element);
        }
        foreach ([[$root, 'Part Y'], [$root, 'Part X'], [$b, 'In B'], [$a, 'In A'], [$a1, 'In A1']] as [$element, $name]) {
            $this->em->persist($this->createPartFor($element, $name));
        }
        $this->em->flush();
        $rootId = $root->getID();
        $this->em->clear();

        /** @var AbstractPartsContainingRepository $repo */
        $repo = $this->em->getRepository($class);
        $root = $this->em->find($class, $rootId);

        //Depth-first, children by name, parts sorted by name inside each element
        $this->assertSame(['Part X', 'Part Y', 'In A', 'In A1', 'In B'],
            array_map(static fn(Part $p) => $p->getName(), $repo->getPartsRecursive($root)));
        $this->assertSame(5, $repo->getPartsCountRecursive($root));

        //Every element of the fixtures gives the same result as the straightforward tree walk
        foreach ($repo->findAll() as $element) {
            $this->assertSame($this->walkParts($repo, $element), $repo->getPartsRecursive($element));
            $this->assertSame($this->walkCount($repo, $element), $repo->getPartsCountRecursive($element));
        }
    }

    public static function multiAssociationClasses(): iterable
    {
        yield StorageLocation::class => [StorageLocation::class];
        yield Supplier::class => [Supplier::class];
    }

    /**
     * Storage locations and suppliers are associated via part lots and orderdetails, so a part can belong to several
     * elements of a subtree. It is then returned (and counted) once for every element, like in the tree walk.
     * @param  class-string<AbstractPartsContainingDBElement>  $class
     */
    #[DataProvider('multiAssociationClasses')]
    public function testRecursiveMethodsWithPartInMultipleElements(string $class): void
    {
        $root = (new $class())->setName('Multi root');
        $child = (new $class())->setName('Multi child')->setParent($root);
        $this->em->persist($root);
        $this->em->persist($child);

        $part = $this->createPartFor($root, 'In both');
        if ($child instanceof StorageLocation) {
            $part->addPartLot((new PartLot())->setStorageLocation($child));
        } else {
            $part->addOrderdetail((new Orderdetail())->setSupplier($child));
        }
        $this->em->persist($part);
        $this->em->flush();
        $rootId = $root->getID();
        $this->em->clear();

        /** @var AbstractPartsContainingRepository $repo */
        $repo = $this->em->getRepository($class);
        $root = $this->em->find($class, $rootId);

        $this->assertSame(['In both', 'In both'],
            array_map(static fn(Part $p) => $p->getName(), $repo->getPartsRecursive($root)));
        $this->assertSame(2, $repo->getPartsCountRecursive($root));
    }

    public function testPartCustomStateGetParts(): void
    {
        $state = (new PartCustomState())->setName('Custom state test');
        $this->em->persist($state);
        //The parts must be found via their custom state (not via their measurement unit)
        $part = (new Part())->setName('With custom state')->setPartCustomState($state)
            ->setCategory($this->em->find(Category::class, 1));
        $this->em->persist($part);
        $this->em->flush();

        $repo = $this->em->getRepository(PartCustomState::class);
        $this->assertSame([$part], $repo->getParts($state));
        $this->assertSame(1, $repo->getPartsCount($state));
    }

    private function createPartFor(AbstractPartsContainingDBElement $element, string $name): Part
    {
        //Every part needs a category
        $part = (new Part())->setName($name)->setCategory($this->em->find(Category::class, 1));
        match (true) {
            $element instanceof Category => $part->setCategory($element),
            $element instanceof Footprint => $part->setFootprint($element),
            $element instanceof Manufacturer => $part->setManufacturer($element),
            $element instanceof MeasurementUnit => $part->setPartUnit($element),
            $element instanceof PartCustomState => $part->setPartCustomState($element),
            $element instanceof StorageLocation => $part->addPartLot((new PartLot())->setStorageLocation($element)),
            $element instanceof Supplier => $part->addOrderdetail((new Orderdetail())->setSupplier($element)),
        };
        return $part;
    }

    private function walkParts(AbstractPartsContainingRepository $repo, AbstractPartsContainingDBElement $element): array
    {
        $parts = $repo->getParts($element);
        foreach ($element->getChildren() as $child) {
            $parts = array_merge($parts, $this->walkParts($repo, $child));
        }
        return $parts;
    }

    private function walkCount(AbstractPartsContainingRepository $repo, AbstractPartsContainingDBElement $element): int
    {
        $count = $repo->getPartsCount($element);
        foreach ($element->getChildren() as $child) {
            $count += $this->walkCount($repo, $child);
        }
        return $count;
    }
}
