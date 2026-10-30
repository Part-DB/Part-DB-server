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

namespace App\Tests\DataTables\Filters\Constraints;

use App\DataTables\Filters\Constraints\NumberConstraint;
use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class NumberConstraintTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    public static function amountSumDataProvider(): iterable
    {
        yield ['<', 0.5, null, ['amount_a']];
        yield ['>', 0.5, null, ['amount_b', 'amount_c']];
        yield ['=', 0.8, null, ['amount_b']];
        yield ['BETWEEN', 0.5, 1.5, ['amount_b']];
        yield ['>=', 1, null, ['amount_c']];
    }

    /**
     * Comparing against a subquery (which has no type affinity in SQLite) must work with decimal values.
     * See https://github.com/Part-DB/Part-DB-server/issues/1620
     */
    #[DataProvider('amountSumDataProvider')]
    public function testDecimalValueOnSubquery(string $operator, float|int $value1, float|int|null $value2, array $expected): void
    {
        $category = $this->entityManager->find(Category::class, 1);
        foreach (['amount_a' => 0.3, 'amount_b' => 0.8, 'amount_c' => 2.5] as $name => $amount) {
            $part = (new Part())->setName($name)->setCategory($category);
            $lot = (new PartLot())->setAmount($amount);
            $part->addPartLot($lot);
            $this->entityManager->persist($part);
        }
        $this->entityManager->flush();

        $constraint = new NumberConstraint('(
                    SELECT COALESCE(SUM(__partLot.amount), 0.0)
                    FROM ' . PartLot::class . ' __partLot
                    WHERE __partLot.part = part.id
                )', identifier: 'amountSum', value1: $value1, operator: $operator, value2: $value2);

        $qb = $this->entityManager->createQueryBuilder()
            ->select('part.name')
            ->from(Part::class, 'part')
            ->where('part.name LIKE :prefix')
            ->setParameter('prefix', 'amount_%')
            ->orderBy('part.name');
        $constraint->apply($qb);

        $this->assertSame($expected, array_column($qb->getQuery()->getArrayResult(), 'name'));
    }
}
