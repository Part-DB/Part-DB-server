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
namespace App\Services\ImportExportSystem;

use App\Entity\Parts\Part;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Service for validating BOM import data with comprehensive validation rules
 * and user-friendly error messages.
 */
class BOMValidationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator
    ) {
    }

    /**
     * Validation result structure
     */
    public static function createValidationResult(): array
    {
        return [
            'errors' => [],
            'warnings' => [],
            'info' => [],
            'is_valid' => true,
            'total_entries' => 0,
            'valid_entries' => 0,
            'invalid_entries' => 0,
        ];
    }

    /**
     * Validate a single BOM entry with comprehensive checks
     */
    public function validateBOMEntry(array $mapped_entry, int $line_number, array $options = []): array
    {
        $result = [
            'line_number' => $line_number,
            'errors' => [],
            'warnings' => [],
            'info' => [],
            'is_valid' => true,
        ];

        // Run all validation rules
        $this->validateRequiredFields($mapped_entry, $result);
        $this->validateDesignatorFormat($mapped_entry, $result);
        $this->validateQuantityFormat($mapped_entry, $result);
        $this->validateDesignatorQuantityMatch($mapped_entry, $result);
        $this->validatePartDBLink($mapped_entry, $result);
        $this->validateComponentName($mapped_entry, $result);
        $this->validatePackageFormat($mapped_entry, $result);
        $this->validateNumericFields($mapped_entry, $result);

        $result['is_valid'] = empty($result['errors']);

        return $result;
    }

    /**
     * Validate multiple BOM entries and provide summary
     */
    public function validateBOMEntries(array $mapped_entries, array $options = []): array
    {
        $result = self::createValidationResult();
        $result['total_entries'] = count($mapped_entries);

        $line_results = [];
        $all_errors = [];
        $all_warnings = [];
        $all_info = [];

        foreach ($mapped_entries as $index => $entry) {
            $line_number = $index + 1;
            $line_result = $this->validateBOMEntry($entry, $line_number, $options);

            $line_results[] = $line_result;

            if ($line_result['is_valid']) {
                $result['valid_entries']++;
            } else {
                $result['invalid_entries']++;
            }

            // Collect all messages
            $all_errors = array_merge($all_errors, $line_result['errors']);
            $all_warnings = array_merge($all_warnings, $line_result['warnings']);
            $all_info = array_merge($all_info, $line_result['info']);
        }

        // Add summary messages
        $this->addSummaryMessages($result, $all_errors, $all_warnings, $all_info);

        $result['errors'] = $all_errors;
        $result['warnings'] = $all_warnings;
        $result['info'] = $all_info;
        $result['line_results'] = $line_results;
        $result['is_valid'] = empty($all_errors);

        return $result;
    }

    /**
     * Validate required fields are present
     */
    private function validateRequiredFields(array $entry, array &$result): void
    {
        $required_fields = ['Designator', 'Quantity'];

        foreach ($required_fields as $field) {
            if (!isset($entry[$field]) || trim($entry[$field]) === '') {
                $result['errors'][] = $this->translator->trans('project.bom_import.validation.errors.required_field_missing', [
                    '%line%' => $result['line_number'],
                    '%field%' => $field
                ]);
            }
        }
    }

    /**
     * Validate designator format and content
     */
    private function validateDesignatorFormat(array $entry, array &$result): void
    {
        if (!isset($entry['Designator']) || trim($entry['Designator']) === '') {
            return; // Already handled by required fields validation
        }

        $designator = trim($entry['Designator']);
        $mountnames = array_map('trim', explode(',', $designator));

        // Remove empty entries
        $mountnames = array_filter($mountnames, fn($name) => !empty($name));

        if (empty($mountnames)) {
            $result['errors'][] = $this->translator->trans('project.bom_import.validation.errors.no_valid_designators', [
                '%line%' => $result['line_number']
            ]);
            return;
        }

        // Validate each mountname format (allow 1-2 uppercase letters, followed by 1+ digits)
        $invalid_mountnames = [];
        foreach ($mountnames as $mountname) {
            if (!preg_match('/^[A-Z]{1,2}[0-9]+$/', $mountname)) {
                $invalid_mountnames[] = $mountname;
            }
        }

        if (!empty($invalid_mountnames)) {
            $result['warnings'][] = $this->translator->trans('project.bom_import.validation.warnings.unusual_designator_format', [
                '%line%' => $result['line_number'],
                '%designators%' => implode(', ', $invalid_mountnames)
            ]);
        }

        // Check for duplicate mountnames within the same line
        $duplicates = array_diff_assoc($mountnames, array_unique($mountnames));
        if (!empty($duplicates)) {
            $result['errors'][] = $this->translator->trans('project.bom_import.validation.errors.duplicate_designators', [
                '%line%' => $result['line_number'],
                '%designators%' => implode(', ', array_unique($duplicates))
            ]);
        }
    }

    /**
     * Validate quantity format and value
     */
    private function validateQuantityFormat(array $entry, array &$result): void
    {
        if (!isset($entry['Quantity']) || trim($entry['Quantity']) === '') {
            return; // Already handled by required fields validation
        }

        $quantity_str = trim($entry['Quantity']);

        // Check if it's a valid number
        if (!is_numeric($quantity_str)) {
            $result['errors'][] = $this->translator->trans('project.bom_import.validation.errors.invalid_quantity', [
                '%line%' => $result['line_number'],
                '%quantity%' => $quantity_str
            ]);
            return;
        }

        $quantity = (float) $quantity_str;

        // Check for reasonable quantity values
        if ($quantity <= 0) {
            $result['errors'][] = $this->translator->trans('project.bom_import.validation.errors.quantity_zero_or_negative', [
                '%line%' => $result['line_number'],
                '%quantity%' => $quantity_str
            ]);
        } elseif ($quantity > 10000) {
            $result['warnings'][] = $this->translator->trans('project.bom_import.validation.warnings.quantity_unusually_high', [
                '%line%' => $result['line_number'],
                '%quantity%' => $quantity_str
            ]);
        }

        // Check if quantity is a whole number when it should be
        if (isset($entry['Designator'])) {
            $designator = trim($entry['Designator']);
            $mountnames = array_map('trim', explode(',', $designator));
            $mountnames = array_filter($mountnames, fn($name) => !empty($name));

            if (count($mountnames) > 0 && $quantity != (int) $quantity) {
                $result['warnings'][] = $this->translator->trans('project.bom_import.validation.warnings.quantity_not_whole_number', [
                    '%line%' => $result['line_number'],
                    '%quantity%' => $quantity_str,
                    '%count%' => count($mountnames)
                ]);
            }
        }
    }

    /**
     * Validate that designator count matches quantity
     */
    private function validateDesignatorQuantityMatch(array $entry, array &$result): void
    {
        if (!isset($entry['Designator']) || !isset($entry['Quantity'])) {
            return; // Already handled by required fields validation
        }

        $designator = trim($entry['Designator']);
        $quantity_str = trim($entry['Quantity']);

        if (!is_numeric($quantity_str)) {
            return; // Already handled by quantity validation
        }

        $mountnames = array_map('trim', explode(',', $designator));
        $mountnames = array_filter($mountnames, fn($name) => !empty($name));
        $mountnames_count = count($mountnames);
        $quantity = (float) $quantity_str;

        if ($mountnames_count !== (int) $quantity) {
            $result['errors'][] = $this->translator->trans('project.bom_import.validation.errors.quantity_designator_mismatch', [
                '%line%' => $result['line_number'],
                '%quantity%' => $quantity_str,
                '%count%' => $mountnames_count,
                '%designators%' => $designator
            ]);
        }
    }

    /**
     * Validate Part-DB ID link
     */
    private function validatePartDBLink(array $entry, array &$result): void
    {
        if (!isset($entry['Part-DB ID']) || trim($entry['Part-DB ID']) === '') {
            return;
        }

        $part_db_id = trim($entry['Part-DB ID']);

        if (!is_numeric($part_db_id)) {
            $result['errors'][] = $this->translator->trans('project.bom_import.validation.errors.invalid_partdb_id', [
                '%line%' => $result['line_number'],
                '%id%' => $part_db_id
            ]);
            return;
        }

        $part_id = (int) $part_db_id;

        if ($part_id <= 0) {
            $result['errors'][] = $this->translator->trans('project.bom_import.validation.errors.partdb_id_zero_or_negative', [
                '%line%' => $result['line_number'],
                '%id%' => $part_id
            ]);
            return;
        }

        // Check if part exists in database
        $existing_part = $this->entityManager->getRepository(Part::class)->find($part_id);
        if (!$existing_part) {
            $result['warnings'][] = $this->translator->trans('project.bom_import.validation.warnings.partdb_id_not_found', [
                '%line%' => $result['line_number'],
                '%id%' => $part_id
            ]);
        } else {
            $result['info'][] = $this->translator->trans('project.bom_import.validation.info.partdb_link_success', [
                '%line%' => $result['line_number'],
                '%name%' => $existing_part->getName(),
                '%id%' => $part_id
            ]);
        }
    }

    /**
     * Validate component name/designation
     */
    private function validateComponentName(array $entry, array &$result): void
    {
        $name_fields = ['MPN', 'Designation', 'Value'];
        $has_name = false;

        foreach ($name_fields as $field) {
            if (isset($entry[$field]) && trim($entry[$field]) !== '') {
                $has_name = true;
                break;
            }
        }

        if (!$has_name) {
            $result['warnings'][] = $this->translator->trans('project.bom_import.validation.warnings.no_component_name', [
                '%line%' => $result['line_number']
            ]);
        }
    }

    /**
     * Validate package format
     */
    private function validatePackageFormat(array $entry, array &$result): void
    {
        if (!isset($entry['Package']) || trim($entry['Package']) === '') {
            return;
        }

        $package = trim($entry['Package']);

        // Check for common package format issues
        if (strlen($package) > 100) {
            $result['warnings'][] = $this->translator->trans('project.bom_import.validation.warnings.package_name_too_long', [
                '%line%' => $result['line_number'],
                '%package%' => $package
            ]);
        }

        // Check for library prefixes (KiCad format)
        if (str_contains($package, ':')) {
            $result['info'][] = $this->translator->trans('project.bom_import.validation.info.library_prefix_detected', [
                '%line%' => $result['line_number'],
                '%package%' => $package
            ]);
        }
    }

    /**
     * Validate numeric fields
     */
    private function validateNumericFields(array $entry, array &$result): void
    {
        $numeric_fields = ['Quantity', 'Part-DB ID'];

        foreach ($numeric_fields as $field) {
            if (isset($entry[$field]) && trim($entry[$field]) !== '') {
                $value = trim($entry[$field]);
                if (!is_numeric($value)) {
                    $result['errors'][] = $this->translator->trans('project.bom_import.validation.errors.non_numeric_field', [
                        '%line%' => $result['line_number'],
                        '%field%' => $field,
                        '%value%' => $value
                    ]);
                }
            }
        }
    }

    /**
     * Add summary messages to validation result
     */
    private function addSummaryMessages(array &$result, array $errors, array $warnings, array $info): void
    {
        $total_entries = $result['total_entries'];
        $valid_entries = $result['valid_entries'];
        $invalid_entries = $result['invalid_entries'];

        // Add summary info
        if ($total_entries > 0) {
            $result['info'][] = $this->translator->trans('project.bom_import.validation.info.import_summary', [
                '%total%' => $total_entries,
                '%valid%' => $valid_entries,
                '%invalid%' => $invalid_entries
            ]);
        }

        // Add error summary
        if (!empty($errors)) {
            $error_count = count($errors);
            $result['errors'][] = $this->translator->trans('project.bom_import.validation.errors.summary', [
                '%count%' => $error_count
            ]);
        }

        // Add warning summary
        if (!empty($warnings)) {
            $warning_count = count($warnings);
            $result['warnings'][] = $this->translator->trans('project.bom_import.validation.warnings.summary', [
                '%count%' => $warning_count
            ]);
        }

        // Add success message if all entries are valid
        if ($total_entries > 0 && $invalid_entries === 0) {
            $result['info'][] = $this->translator->trans('project.bom_import.validation.info.all_valid');
        }
    }

    /**
     * Get user-friendly error message for a validation result
     */
    public function getErrorMessage(array $validation_result): string
    {
        if ($validation_result['is_valid']) {
            return '';
        }

        $messages = [];

        if (!empty($validation_result['errors'])) {
            $messages[] = 'Errors:';
            foreach ($validation_result['errors'] as $error) {
                $messages[] = '• ' . $error;
            }
        }

        if (!empty($validation_result['warnings'])) {
            $messages[] = 'Warnings:';
            foreach ($validation_result['warnings'] as $warning) {
                $messages[] = '• ' . $warning;
            }
        }

        return implode("\n", $messages);
    }

    /**
     * Get validation statistics
     */
    public function getValidationStats(array $validation_result): array
    {
        return [
            'total_entries' => $validation_result['total_entries'] ?? 0,
            'valid_entries' => $validation_result['valid_entries'] ?? 0,
            'invalid_entries' => $validation_result['invalid_entries'] ?? 0,
            'error_count' => count($validation_result['errors'] ?? []),
            'warning_count' => count($validation_result['warnings'] ?? []),
            'info_count' => count($validation_result['info'] ?? []),
            'success_rate' => $validation_result['total_entries'] > 0
                ? round(($validation_result['valid_entries'] / $validation_result['total_entries']) * 100, 1)
                : 0,
        ];
    }
}