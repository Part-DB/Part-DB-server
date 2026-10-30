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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
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

    /**
     * Creates a new (unsaved) lot for the given fixture part in storage location 1, which already contains "Part 3"
     * (see PartFixtures).
     * The lot must be validated with the validator from the current container (see validateWithCurrentContainer()),
     * as the static validator uses the entity manager of an older kernel.
     */
    private function createNewLotInLocation1(string $partName, callable $configureLocation): PartLot
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $location = $em->find(StorageLocation::class, 1);
        $configureLocation($location);

        $part = $em->getRepository(Part::class)->findOneBy(['name' => $partName]);
        $this->assertInstanceOf(Part::class, $part);

        $lot = new PartLot();
        $lot->setStorageLocation($location);
        $part->addPartLot($lot);

        return $lot;
    }

    private function validateWithCurrentContainer(PartLot $lot): ConstraintViolationListInterface
    {
        return self::getContainer()->get('validator')->validate($lot, new ValidPartLot());
    }

    public function testOnlySinglePartRejectsNewLotOfOtherPart(): void
    {
        $lot = $this->createNewLotInLocation1('Part 1', fn (StorageLocation $l) => $l->setOnlySinglePart(true));

        $violations = $this->validateWithCurrentContainer($lot);
        $this->assertCount(1, $violations);
        $this->assertSame('storage_location', $violations[0]->getPropertyPath());
        $this->assertSame('validator.part_lot.single_part', $violations[0]->getMessageTemplate());
    }

    public function testOnlySinglePartAllowsNewLotOfSamePart(): void
    {
        $lot = $this->createNewLotInLocation1('Part 3', fn (StorageLocation $l) => $l->setOnlySinglePart(true));

        $violations = $this->validateWithCurrentContainer($lot);
        $this->assertCount(0, $violations);
    }

    public function testLimitToExistingPartsRejectsNewLotOfOtherPart(): void
    {
        $lot = $this->createNewLotInLocation1('Part 1', fn (StorageLocation $l) => $l->setLimitToExistingParts(true));

        $violations = $this->validateWithCurrentContainer($lot);
        $this->assertCount(1, $violations);
        $this->assertSame('validator.part_lot.only_existing', $violations[0]->getMessageTemplate());
    }

    public function testLimitToExistingPartsAllowsNewLotOfExistingPart(): void
    {
        $lot = $this->createNewLotInLocation1('Part 3', fn (StorageLocation $l) => $l->setLimitToExistingParts(true));

        $violations = $this->validateWithCurrentContainer($lot);
        $this->assertCount(0, $violations);
    }
}
