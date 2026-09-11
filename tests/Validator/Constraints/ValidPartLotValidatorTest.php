<?php

declare(strict_types=1);

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

namespace App\Tests\Validator\Constraints;

use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\StorageLocation;
use App\Validator\Constraints\ValidPartLot;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ValidPartLotValidatorTest extends WebTestCase
{
    private static ValidatorInterface $validator;

    public static function setUpBeforeClass(): void
    {
        self::bootKernel();
        self::$validator = self::getContainer()->get('validator');
    }

    public function testPartLotWithoutStorageLocationIsValid(): void
    {
        $lot = new PartLot();
        $lot->setPart(new Part());
        // No storage location set → validation should pass without any location checks

        $violations = self::$validator->validate($lot, new ValidPartLot());
        $this->assertCount(0, $violations);
    }

    public function testPartLotWithNonFullNonRestrictedStorageLocationIsValid(): void
    {
        $lot = new PartLot();
        $lot->setPart(new Part());

        $location = new StorageLocation();
        // Default: not full, not limited — should be valid
        $lot->setStorageLocation($location);

        $violations = self::$validator->validate($lot, new ValidPartLot());
        $this->assertCount(0, $violations);
    }

    public function testPartLotWithFullLocationAndNewLotRaisesViolation(): void
    {
        $lot = new PartLot();
        $lot->setPart(new Part());

        $location = new StorageLocation();
        $location->setIsFull(true);
        $lot->setStorageLocation($location);
        // The lot has no ID (new entity), so "parts" is empty, and a full location will reject it

        $violations = self::$validator->validate($lot, new ValidPartLot());
        // Should raise a violation because the location is full and the part is not in the existing parts list
        $this->assertGreaterThan(0, count($violations));
    }

    public function testNonPartLotValueThrowsException(): void
    {
        $this->expectException(\Symfony\Component\Form\Exception\UnexpectedTypeException::class);
        self::$validator->validate('not a part lot', new ValidPartLot());
    }

    public function testPartLotWithFullLocationRaisesNamedViolation(): void
    {
        $lot = new PartLot();
        $lot->setPart(new Part());

        $location = new StorageLocation();
        $location->setIsFull(true);
        $lot->setStorageLocation($location);

        $violations = self::$validator->validate($lot, new ValidPartLot());
        // Expect exactly one violation on the storage_location path
        $this->assertCount(1, $violations);
        $this->assertSame('storage_location', $violations[0]->getPropertyPath());
        $this->assertStringContainsString('location_full', $violations[0]->getMessageTemplate());
    }

    public function testLimitToExistingPartsWithNewLotRaisesViolation(): void
    {
        $lot = new PartLot();
        $lot->setPart(new Part());

        $location = new StorageLocation();
        $location->setLimitToExistingParts(true);
        $lot->setStorageLocation($location);

        // New lot (no ID) → parts collection is empty → part is not in the list → violation
        $violations = self::$validator->validate($lot, new ValidPartLot());
        $this->assertCount(1, $violations);
        $this->assertSame('storage_location', $violations[0]->getPropertyPath());
        $this->assertSame('validator.part_lot.only_existing', $violations[0]->getMessageTemplate());
    }

    // NOTE: The 'location_full.no_increase' violation (raised when a lot's amount
    // is increased while its storage location is marked full) requires the entity to
    // carry a real Doctrine originalEntityData snapshot, which is only set after an
    // actual persist+flush. Testing that path belongs in a database integration test.
}
