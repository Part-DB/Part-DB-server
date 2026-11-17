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

namespace App\Tests\DataTables\Filters\Constraints\Part;

use App\DataTables\Filters\Constraints\Part\ParameterValueConstraint;
use App\Entity\Parameters\PartParameter;
use App\Entity\Parts\Part;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ParameterValueConstraintTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testRangeIntersectRange(): void
    {
        $part = $this->entityManager->find(Part::class, 1);
        $ranges = ['range_a' => [-55, 125], 'range_b' => [-40, 85], 'range_c' => [0, 400], 'range_d' => [130, null], 'range_e' => [null, 90]];
        foreach ($ranges as $name => [$min, $max]) {
            $parameter = (new PartParameter())->setName($name)->setValueMin($min)->setValueMax($max);
            $part->addParameter($parameter);
            $this->entityManager->persist($parameter);
        }
        $this->entityManager->flush();

        $constraint = new ParameterValueConstraint('param');
        $constraint->setOperator('RANGE_INTERSECT_RANGE');
        $constraint->setValue1(100);
        $constraint->setValue2(150);

        $qb = $this->entityManager->createQueryBuilder()
            ->select('param.name')
            ->from(PartParameter::class, 'param')
            ->where('param.name LIKE :prefix')
            ->setParameter('prefix', 'range_%')
            ->orderBy('param.name');
        $constraint->apply($qb);

        $this->assertSame(['range_a', 'range_c', 'range_d'], array_column($qb->getQuery()->getArrayResult(), 'name'));
    }
}
