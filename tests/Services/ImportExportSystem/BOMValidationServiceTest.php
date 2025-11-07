<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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
namespace App\Tests\Services\ImportExportSystem;

use App\Entity\Parts\Part;
use App\Services\ImportExportSystem\BOMValidationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @see \App\Services\ImportExportSystem\BOMValidationService
 */
class BOMValidationServiceTest extends WebTestCase
{
    private BOMValidationService $validationService;
    private EntityManagerInterface $entityManager;
    private TranslatorInterface $translator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->translator = self::getContainer()->get(TranslatorInterface::class);
        $this->validationService = new BOMValidationService($this->entityManager, $this->translator);
    }

    public function testValidateBOMEntryWithValidData(): void
    {
        $entry = [
            'Designator' => 'R1,C2,R3',
            'Quantity' => '3',
            'MPN' => 'RES-10K',
            'Package' => '0603',
            'Value' => '10k',
        ];

        $result = $this->validationService->validateBOMEntry($entry, 1);

        $this->assertTrue($result['is_valid']);
        $this->assertEmpty($result['errors']);
        $this->assertEquals(1, $result['line_number']);
    }

    public function testValidateBOMEntryWithMissingRequiredFields(): void
    {
        $entry = [
            'MPN' => 'RES-10K',
            'Package' => '0603',
        ];

        $result = $this->validationService->validateBOMEntry($entry, 1);

        $this->assertFalse($result['is_valid']);
        $this->assertCount(2, $result['errors']);
        $this->assertStringContainsString('Designator', (string) $result['errors'][0]);
        $this->assertStringContainsString('Quantity', (string) $result['errors'][1]);
    }

    public function testValidateBOMEntryWithQuantityMismatch(): void
    {
        $entry = [
            'Designator' => 'R1,C2,R3,C4',
            'Quantity' => '3',
            'MPN' => 'RES-10K',
        ];

        $result = $this->validationService->validateBOMEntry($entry, 1);

        $this->assertFalse($result['is_valid']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('Mismatch between quantity and component references', (string) $result['errors'][0]);
    }

    public function testValidateBOMEntryWithInvalidQuantity(): void
    {
        $entry = [
            'Designator' => 'R1',
            'Quantity' => 'abc',
            'MPN' => 'RES-10K',
        ];

        $result = $this->validationService->validateBOMEntry($entry, 1);

        $this->assertFalse($result['is_valid']);
        $this->assertGreaterThanOrEqual(1, count($result['errors']));
        $this->assertStringContainsString('not a valid number', implode(' ', array_map('strval', $result['errors'])));
    }

    public function testValidateBOMEntryWithZeroQuantity(): void
    {
        $entry = [
            'Designator' => 'R1',
            'Quantity' => '0',
            'MPN' => 'RES-10K',
        ];

        $result = $this->validationService->validateBOMEntry($entry, 1);

        $this->assertFalse($result['is_valid']);
        $this->assertGreaterThanOrEqual(1, count($result['errors']));
        $this->assertStringContainsString('must be greater than 0', implode(' ', array_map('strval', $result['errors'])));
    }

    public function testValidateBOMEntryWithDuplicateDesignators(): void
    {
        $entry = [
            'Designator' => 'R1,R1,C2',
            'Quantity' => '3',
            'MPN' => 'RES-10K',
        ];

        $result = $this->validationService->validateBOMEntry($entry, 1);

        $this->assertFalse($result['is_valid']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('Duplicate component references', (string) $result['errors'][0]);
    }

    public function testValidateBOMEntryWithInvalidDesignatorFormat(): void
    {
        $entry = [
            'Designator' => 'R1,invalid,C2',
            'Quantity' => '3',
            'MPN' => 'RES-10K',
        ];

        $result = $this->validationService->validateBOMEntry($entry, 1);

        $this->assertTrue($result['is_valid']); // Warnings don't make it invalid
        $this->assertCount(1, $result['warnings']);
        $this->assertStringContainsString('unusual format', (string) $result['warnings'][0]);
    }

    public function testValidateBOMEntryWithEmptyDesignator(): void
    {
        $entry = [
            'Designator' => '',
            'Quantity' => '1',
            'MPN' => 'RES-10K',
        ];

        $result = $this->validationService->validateBOMEntry($entry, 1);

        $this->assertFalse($result['is_valid']);
        $this->assertGreaterThanOrEqual(1, count($result['errors']));
        $this->assertStringContainsString('Required field "Designator" is missing or empty', implode(' ', array_map('strval', $result['errors'])));
    }

    public function testValidateBOMEntryWithInvalidPartDBID(): void
    {
        $entry = [
            'Designator' => 'R1',
            'Quantity' => '1',
            'MPN' => 'RES-10K',
            'Part-DB ID' => 'abc',
        ];

        $result = $this->validationService->validateBOMEntry($entry, 1);

        $this->assertFalse($result['is_valid']);
        $this->assertGreaterThanOrEqual(1, count($result['errors']));
        $this->assertStringContainsString('not a valid number', implode(' ', array_map('strval', $result['errors'])));
    }

    public function testValidateBOMEntryWithNonExistentPartDBID(): void
    {
        $entry = [
            'Designator' => 'R1',
            'Quantity' => '1',
            'MPN' => 'RES-10K',
            'Part-DB ID' => '999999', // Use very high ID that doesn't exist
        ];

        $result = $this->validationService->validateBOMEntry($entry, 1);

        $this->assertTrue($result['is_valid']); // Warnings don't make it invalid
        $this->assertCount(1, $result['warnings']);
        $this->assertStringContainsString('not found in database', (string) $result['warnings'][0]);
    }

    public function testValidateBOMEntryWithNoComponentName(): void
    {
        $entry = [
            'Designator' => 'R1',
            'Quantity' => '1',
            'Package' => '0603',
        ];

        $result = $this->validationService->validateBOMEntry($entry, 1);

        $this->assertTrue($result['is_valid']); // Warnings don't make it invalid
        $this->assertCount(1, $result['warnings']);
        $this->assertStringContainsString('No component name/designation', (string) $result['warnings'][0]);
    }

    public function testValidateBOMEntryWithLongPackageName(): void
    {
        $entry = [
            'Designator' => 'R1',
            'Quantity' => '1',
            'MPN' => 'RES-10K',
            'Package' => str_repeat('A', 150), // Very long package name
        ];

        $result = $this->validationService->validateBOMEntry($entry, 1);

        $this->assertTrue($result['is_valid']); // Warnings don't make it invalid
        $this->assertCount(1, $result['warnings']);
        $this->assertStringContainsString('unusually long', (string) $result['warnings'][0]);
    }

    public function testValidateBOMEntryWithLibraryPrefix(): void
    {
        $entry = [
            'Designator' => 'R1',
            'Quantity' => '1',
            'MPN' => 'RES-10K',
            'Package' => 'Resistor_SMD:R_0603_1608Metric',
        ];

        $result = $this->validationService->validateBOMEntry($entry, 1);

        $this->assertTrue($result['is_valid']);
        $this->assertCount(1, $result['info']);
        $this->assertStringContainsString('library prefix', $result['info'][0]);
    }

    public function testValidateBOMEntriesWithMultipleEntries(): void
    {
        $entries = [
            [
                'Designator' => 'R1',
                'Quantity' => '1',
                'MPN' => 'RES-10K',
            ],
            [
                'Designator' => 'C1,C2',
                'Quantity' => '2',
                'MPN' => 'CAP-100nF',
            ],
        ];

        $result = $this->validationService->validateBOMEntries($entries);

        $this->assertTrue($result['is_valid']);
        $this->assertEquals(2, $result['total_entries']);
        $this->assertEquals(2, $result['valid_entries']);
        $this->assertEquals(0, $result['invalid_entries']);
        $this->assertCount(2, $result['line_results']);
    }

    public function testValidateBOMEntriesWithMixedResults(): void
    {
        $entries = [
            [
                'Designator' => 'R1',
                'Quantity' => '1',
                'MPN' => 'RES-10K',
            ],
            [
                'Designator' => 'C1,C2',
                'Quantity' => '1', // Mismatch
                'MPN' => 'CAP-100nF',
            ],
        ];

        $result = $this->validationService->validateBOMEntries($entries);

        $this->assertFalse($result['is_valid']);
        $this->assertEquals(2, $result['total_entries']);
        $this->assertEquals(1, $result['valid_entries']);
        $this->assertEquals(1, $result['invalid_entries']);
        $this->assertCount(1, $result['errors']);
    }

    public function testGetValidationStats(): void
    {
        $validation_result = [
            'total_entries' => 10,
            'valid_entries' => 8,
            'invalid_entries' => 2,
            'errors' => ['Error 1', 'Error 2'],
            'warnings' => ['Warning 1'],
            'info' => ['Info 1', 'Info 2'],
        ];

        $stats = $this->validationService->getValidationStats($validation_result);

        $this->assertEquals(10, $stats['total_entries']);
        $this->assertEquals(8, $stats['valid_entries']);
        $this->assertEquals(2, $stats['invalid_entries']);
        $this->assertEquals(2, $stats['error_count']);
        $this->assertEquals(1, $stats['warning_count']);
        $this->assertEquals(2, $stats['info_count']);
        $this->assertEquals(80.0, $stats['success_rate']);
    }

    public function testGetErrorMessage(): void
    {
        $validation_result = [
            'is_valid' => false,
            'errors' => ['Error 1', 'Error 2'],
            'warnings' => ['Warning 1'],
        ];

        $message = $this->validationService->getErrorMessage($validation_result);

        $this->assertStringContainsString('Errors:', $message);
        $this->assertStringContainsString('• Error 1', $message);
        $this->assertStringContainsString('• Error 2', $message);
        $this->assertStringContainsString('Warnings:', $message);
        $this->assertStringContainsString('• Warning 1', $message);
    }

    public function testGetErrorMessageWithValidResult(): void
    {
        $validation_result = [
            'is_valid' => true,
            'errors' => [],
            'warnings' => [],
        ];

        $message = $this->validationService->getErrorMessage($validation_result);

        $this->assertEquals('', $message);
    }
}