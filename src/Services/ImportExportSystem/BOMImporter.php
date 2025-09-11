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
use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use League\Csv\Reader;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @see \App\Tests\Services\ImportExportSystem\BOMImporterTest
 */
class BOMImporter
{

    private const MAP_KICAD_PCB_FIELDS = [
        0 => 'Id',
        1 => 'Designator',
        2 => 'Package',
        3 => 'Quantity',
        4 => 'Designation',
        5 => 'Supplier and ref',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly BOMValidationService $validationService
    ) {
    }

    protected function configureOptions(OptionsResolver $resolver): OptionsResolver
    {
        $resolver->setRequired('type');
        $resolver->setAllowedValues('type', ['kicad_pcbnew', 'kicad_schematic']);

        // For flexible schematic import with field mapping
        $resolver->setDefined(['field_mapping', 'field_priorities', 'delimiter']);
        $resolver->setDefault('delimiter', ',');
        $resolver->setDefault('field_priorities', []);
        $resolver->setAllowedTypes('field_mapping', 'array');
        $resolver->setAllowedTypes('field_priorities', 'array');
        $resolver->setAllowedTypes('delimiter', 'string');

        return $resolver;
    }

    /**
     * Converts the given file into an array of BOM entries using the given options and save them into the given project.
     * The changes are not saved into the database yet.
     * @return ProjectBOMEntry[]
     */
    public function importFileIntoProject(File $file, Project $project, array $options): array
    {
        $bom_entries = $this->fileToBOMEntries($file, $options);

        //Assign the bom_entries to the project
        foreach ($bom_entries as $bom_entry) {
            $project->addBomEntry($bom_entry);
        }

        return $bom_entries;
    }

    /**
     * Converts the given file into an array of BOM entries using the given options.
     * @return ProjectBOMEntry[]
     */
    public function fileToBOMEntries(File $file, array $options): array
    {
        return $this->stringToBOMEntries($file->getContent(), $options);
    }

    /**
     * Validate BOM data before importing
     * @return array Validation result with errors, warnings, and info
     */
    public function validateBOMData(string $data, array $options): array
    {
        $resolver = new OptionsResolver();
        $resolver = $this->configureOptions($resolver);
        $options = $resolver->resolve($options);

        return match ($options['type']) {
            'kicad_pcbnew' => $this->validateKiCADPCB($data),
            'kicad_schematic' => $this->validateKiCADSchematicData($data, $options),
            default => throw new InvalidArgumentException('Invalid import type!'),
        };
    }

    /**
     * Import string data into an array of BOM entries, which are not yet assigned to a project.
     * @param  string  $data The data to import
     * @param  array  $options An array of options
     * @return ProjectBOMEntry[] An array of imported entries
     */
    public function stringToBOMEntries(string $data, array $options): array
    {
        $resolver = new OptionsResolver();
        $resolver = $this->configureOptions($resolver);
        $options = $resolver->resolve($options);

        return match ($options['type']) {
            'kicad_pcbnew' => $this->parseKiCADPCB($data),
            'kicad_schematic' => $this->parseKiCADSchematic($data, $options),
            default => throw new InvalidArgumentException('Invalid import type!'),
        };
    }

    private function parseKiCADPCB(string $data): array
    {
        $csv = Reader::createFromString($data);
        $csv->setDelimiter(';');
        $csv->setHeaderOffset(0);

        $bom_entries = [];

        foreach ($csv->getRecords() as $offset => $entry) {
            //Translate the german field names to english
            $entry = $this->normalizeColumnNames($entry);

            //Ensure that the entry has all required fields
            if (!isset($entry['Designator'])) {
                throw new \UnexpectedValueException('Designator missing at line ' . ($offset + 1) . '!');
            }
            if (!isset($entry['Package'])) {
                throw new \UnexpectedValueException('Package missing at line ' . ($offset + 1) . '!');
            }
            if (!isset($entry['Designation'])) {
                throw new \UnexpectedValueException('Designation missing at line ' . ($offset + 1) . '!');
            }
            if (!isset($entry['Quantity'])) {
                throw new \UnexpectedValueException('Quantity missing at line ' . ($offset + 1) . '!');
            }

            $bom_entry = new ProjectBOMEntry();
            $bom_entry->setName($entry['Designation'] . ' (' . $entry['Package'] . ')');
            $bom_entry->setMountnames($entry['Designator'] ?? '');
            $bom_entry->setComment($entry['Supplier and ref'] ?? '');
            $bom_entry->setQuantity((float) ($entry['Quantity'] ?? 1));

            $bom_entries[] = $bom_entry;
        }

        return $bom_entries;
    }

    /**
     * Validate KiCad PCB data
     */
    private function validateKiCADPCB(string $data): array
    {
        $csv = Reader::createFromString($data);
        $csv->setDelimiter(';');
        $csv->setHeaderOffset(0);

        $mapped_entries = [];

        foreach ($csv->getRecords() as $offset => $entry) {
            // Translate the german field names to english
            $entry = $this->normalizeColumnNames($entry);
            $mapped_entries[] = $entry;
        }

        return $this->validationService->validateBOMEntries($mapped_entries);
    }

    /**
     * Validate KiCad schematic data
     */
    private function validateKiCADSchematicData(string $data, array $options): array
    {
        $delimiter = $options['delimiter'] ?? ',';
        $field_mapping = $options['field_mapping'] ?? [];
        $field_priorities = $options['field_priorities'] ?? [];

        // Handle potential BOM (Byte Order Mark) at the beginning
        $data = preg_replace('/^\xEF\xBB\xBF/', '', $data);

        $csv = Reader::createFromString($data);
        $csv->setDelimiter($delimiter);
        $csv->setHeaderOffset(0);

        // Handle quoted fields properly
        $csv->setEscape('\\');
        $csv->setEnclosure('"');

        $mapped_entries = [];

        foreach ($csv->getRecords() as $offset => $entry) {
            // Apply field mapping to translate column names
            $mapped_entry = $this->applyFieldMapping($entry, $field_mapping, $field_priorities);

            // Extract footprint package name if it contains library prefix
            if (isset($mapped_entry['Package']) && str_contains($mapped_entry['Package'], ':')) {
                $mapped_entry['Package'] = explode(':', $mapped_entry['Package'], 2)[1];
            }

            $mapped_entries[] = $mapped_entry;
        }

        return $this->validationService->validateBOMEntries($mapped_entries, $options);
    }

    /**
     * This function uses the order of the fields in the CSV files to make them locale independent.
     * @param  array  $entry
     * @return array
     */
    private function normalizeColumnNames(array $entry): array
    {
        $out = [];

        //Map the entry order to the correct column names
        foreach (array_values($entry) as $index => $field) {
            if ($index > 5) {
                break;
            }

            //@phpstan-ignore-next-line We want to keep this check just to be safe when something changes
            $new_index = self::MAP_KICAD_PCB_FIELDS[$index] ?? throw new \UnexpectedValueException('Invalid field index!');
            $out[$new_index] = $field;
        }

        return $out;
    }

    /**
     * Parse KiCad schematic BOM with flexible field mapping
     */
    private function parseKiCADSchematic(string $data, array $options = []): array
    {
        $delimiter = $options['delimiter'] ?? ',';
        $field_mapping = $options['field_mapping'] ?? [];
        $field_priorities = $options['field_priorities'] ?? [];

        // Handle potential BOM (Byte Order Mark) at the beginning
        $data = preg_replace('/^\xEF\xBB\xBF/', '', $data);

        $csv = Reader::createFromString($data);
        $csv->setDelimiter($delimiter);
        $csv->setHeaderOffset(0);

        // Handle quoted fields properly
        $csv->setEscape('\\');
        $csv->setEnclosure('"');

        $bom_entries = [];
        $entries_by_key = []; // Track entries by name+part combination
        $mapped_entries = []; // Collect all mapped entries for validation

        foreach ($csv->getRecords() as $offset => $entry) {
            // Apply field mapping to translate column names
            $mapped_entry = $this->applyFieldMapping($entry, $field_mapping, $field_priorities);

            // Extract footprint package name if it contains library prefix
            if (isset($mapped_entry['Package']) && str_contains($mapped_entry['Package'], ':')) {
                $mapped_entry['Package'] = explode(':', $mapped_entry['Package'], 2)[1];
            }

            $mapped_entries[] = $mapped_entry;
        }

        // Validate all entries before processing
        $validation_result = $this->validationService->validateBOMEntries($mapped_entries, $options);

        // Log validation results
        $this->logger->info('BOM import validation completed', [
            'total_entries' => $validation_result['total_entries'],
            'valid_entries' => $validation_result['valid_entries'],
            'invalid_entries' => $validation_result['invalid_entries'],
            'error_count' => count($validation_result['errors']),
            'warning_count' => count($validation_result['warnings']),
        ]);

        // If there are validation errors, throw an exception with detailed messages
        if (!empty($validation_result['errors'])) {
            $error_message = $this->validationService->getErrorMessage($validation_result);
            throw new \UnexpectedValueException("BOM import validation failed:\n" . $error_message);
        }

        // Process validated entries
        foreach ($mapped_entries as $offset => $mapped_entry) {

            // Set name - prefer MPN, fall back to Value, then default format
            $mpn = trim($mapped_entry['MPN'] ?? '');
            $designation = trim($mapped_entry['Designation'] ?? '');
            $value = trim($mapped_entry['Value'] ?? '');

            // Use the first non-empty value, or 'Unknown Component' if all are empty
            $name = '';
            if (!empty($mpn)) {
                $name = $mpn;
            } elseif (!empty($designation)) {
                $name = $designation;
            } elseif (!empty($value)) {
                $name = $value;
            } else {
                $name = 'Unknown Component';
            }

            if (isset($mapped_entry['Package']) && !empty(trim($mapped_entry['Package']))) {
                $name .= ' (' . trim($mapped_entry['Package']) . ')';
            }

            // Set mountnames and quantity
            // The Designator field contains comma-separated mount names for all instances
            $designator = trim($mapped_entry['Designator']);
            $quantity = (float) $mapped_entry['Quantity'];

            // Get mountnames array (validation already ensured they match quantity)
            $mountnames_array = array_map('trim', explode(',', $designator));

            // Try to link existing Part-DB part if ID is provided
            $part = null;
            if (isset($mapped_entry['Part-DB ID']) && !empty($mapped_entry['Part-DB ID'])) {
                $partDbId = (int) $mapped_entry['Part-DB ID'];
                $existingPart = $this->entityManager->getRepository(Part::class)->find($partDbId);

                if ($existingPart) {
                    $part = $existingPart;
                    // Update name with actual part name
                    $name = $existingPart->getName();
                }
            }

            // Create unique key for this entry (name + part ID)
            $entry_key = $name . '|' . ($part ? $part->getID() : 'null');

            // Check if we already have an entry with the same name and part
            if (isset($entries_by_key[$entry_key])) {
                // Merge with existing entry
                $existing_entry = $entries_by_key[$entry_key];

                // Combine mountnames
                $existing_mountnames = $existing_entry->getMountnames();
                $combined_mountnames = $existing_mountnames . ',' . $designator;
                $existing_entry->setMountnames($combined_mountnames);

                // Add quantities
                $existing_quantity = $existing_entry->getQuantity();
                $existing_entry->setQuantity($existing_quantity + $quantity);

                $this->logger->info('Merged duplicate BOM entry', [
                    'name' => $name,
                    'part_id' => $part ? $part->getID() : null,
                    'original_quantity' => $existing_quantity,
                    'added_quantity' => $quantity,
                    'new_quantity' => $existing_quantity + $quantity,
                    'original_mountnames' => $existing_mountnames,
                    'added_mountnames' => $designator,
                ]);

                continue; // Skip creating new entry
            }

            // Create new BOM entry
            $bom_entry = new ProjectBOMEntry();
            $bom_entry->setName($name);
            $bom_entry->setMountnames($designator);
            $bom_entry->setQuantity($quantity);

            if ($part) {
                $bom_entry->setPart($part);
            }

            // Set comment with additional info
            $comment_parts = [];
            if (isset($mapped_entry['Value']) && $mapped_entry['Value'] !== ($mapped_entry['MPN'] ?? '')) {
                $comment_parts[] = 'Value: ' . $mapped_entry['Value'];
            }
            if (isset($mapped_entry['MPN'])) {
                $comment_parts[] = 'MPN: ' . $mapped_entry['MPN'];
            }
            if (isset($mapped_entry['Manufacturer'])) {
                $comment_parts[] = 'Manf: ' . $mapped_entry['Manufacturer'];
            }
            if (isset($mapped_entry['LCSC'])) {
                $comment_parts[] = 'LCSC: ' . $mapped_entry['LCSC'];
            }
            if (isset($mapped_entry['Supplier and ref'])) {
                $comment_parts[] = $mapped_entry['Supplier and ref'];
            }

            if ($part) {
                $comment_parts[] = "Part-DB ID: " . $part->getID();
            } elseif (isset($mapped_entry['Part-DB ID']) && !empty($mapped_entry['Part-DB ID'])) {
                $comment_parts[] = "Part-DB ID: " . $mapped_entry['Part-DB ID'] . " (NOT FOUND)";
            }

            $bom_entry->setComment(implode(', ', $comment_parts));

            $bom_entries[] = $bom_entry;
            $entries_by_key[$entry_key] = $bom_entry;
        }

        return $bom_entries;
    }

    /**
     * Get all available field mapping targets with descriptions
     */
    public function getAvailableFieldTargets(): array
    {
        $targets = [
            'Designator' => [
                'label' => 'Designator',
                'description' => 'Component reference designators (e.g., R1, C2, U3)',
                'required' => true,
                'multiple' => false,
            ],
            'Quantity' => [
                'label' => 'Quantity',
                'description' => 'Number of components',
                'required' => true,
                'multiple' => false,
            ],
            'Designation' => [
                'label' => 'Designation',
                'description' => 'Component designation/part number',
                'required' => false,
                'multiple' => true,
            ],
            'Value' => [
                'label' => 'Value',
                'description' => 'Component value (e.g., 10k, 100nF)',
                'required' => false,
                'multiple' => true,
            ],
            'Package' => [
                'label' => 'Package',
                'description' => 'Component package/footprint',
                'required' => false,
                'multiple' => true,
            ],
            'MPN' => [
                'label' => 'MPN',
                'description' => 'Manufacturer Part Number',
                'required' => false,
                'multiple' => true,
            ],
            'Manufacturer' => [
                'label' => 'Manufacturer',
                'description' => 'Component manufacturer name',
                'required' => false,
                'multiple' => true,
            ],
            'Part-DB ID' => [
                'label' => 'Part-DB ID',
                'description' => 'Existing Part-DB part ID for linking',
                'required' => false,
                'multiple' => false,
            ],
            'Comment' => [
                'label' => 'Comment',
                'description' => 'Additional component information',
                'required' => false,
                'multiple' => true,
            ],
        ];

        // Add dynamic supplier fields based on available suppliers in the database
        $suppliers = $this->entityManager->getRepository(\App\Entity\Parts\Supplier::class)->findAll();
        foreach ($suppliers as $supplier) {
            $supplierName = $supplier->getName();
            $targets[$supplierName . ' SPN'] = [
                'label' => $supplierName . ' SPN',
                'description' => "Supplier part number for {$supplierName}",
                'required' => false,
                'multiple' => true,
                'supplier_id' => $supplier->getID(),
            ];
        }

        return $targets;
    }

    /**
     * Get suggested field mappings based on common field names
     */
    public function getSuggestedFieldMapping(array $detected_fields): array
    {
        $suggestions = [];

        $field_patterns = [
            'Part-DB ID' => ['part-db id', 'partdb_id', 'part_db_id', 'db_id', 'partdb'],
            'Designator' => ['reference', 'ref', 'designator', 'component', 'comp'],
            'Quantity' => ['qty', 'quantity', 'count', 'number', 'amount'],
            'Value' => ['value', 'val', 'component_value'],
            'Designation' => ['designation', 'part_number', 'partnumber', 'part'],
            'Package' => ['footprint', 'package', 'housing', 'fp'],
            'MPN' => ['mpn', 'part_number', 'partnumber', 'manf#', 'mfr_part_number', 'manufacturer_part'],
            'Manufacturer' => ['manufacturer', 'manf', 'mfr', 'brand', 'vendor'],
            'Comment' => ['comment', 'comments', 'note', 'notes', 'description'],
        ];

        // Add supplier-specific patterns
        $suppliers = $this->entityManager->getRepository(\App\Entity\Parts\Supplier::class)->findAll();
        foreach ($suppliers as $supplier) {
            $supplierName = $supplier->getName();
            $supplierLower = strtolower($supplierName);

            // Create patterns for each supplier
            $field_patterns[$supplierName . ' SPN'] = [
                $supplierLower,
                $supplierLower . '#',
                $supplierLower . '_part',
                $supplierLower . '_number',
                $supplierLower . 'pn',
                $supplierLower . '_spn',
                $supplierLower . ' spn',
                // Common abbreviations
                $supplierLower === 'mouser' ? 'mouser' : null,
                $supplierLower === 'digikey' ? 'dk' : null,
                $supplierLower === 'farnell' ? 'farnell' : null,
                $supplierLower === 'rs' ? 'rs' : null,
                $supplierLower === 'lcsc' ? 'lcsc' : null,
            ];

            // Remove null values
            $field_patterns[$supplierName . ' SPN'] = array_filter($field_patterns[$supplierName . ' SPN'], fn($value) => $value !== null);
        }

        foreach ($detected_fields as $field) {
            $field_lower = strtolower(trim($field));

            foreach ($field_patterns as $target => $patterns) {
                foreach ($patterns as $pattern) {
                    if (str_contains($field_lower, $pattern)) {
                        $suggestions[$field] = $target;
                        break 2; // Break both loops
                    }
                }
            }
        }

        return $suggestions;
    }

    /**
     * Validate field mapping configuration
     */
    public function validateFieldMapping(array $field_mapping, array $detected_fields): array
    {
        $errors = [];
        $warnings = [];
        $available_targets = $this->getAvailableFieldTargets();

        // Check for required fields
        $mapped_targets = array_values($field_mapping);
        $required_fields = ['Designator', 'Quantity'];

        foreach ($required_fields as $required) {
            if (!in_array($required, $mapped_targets, true)) {
                $errors[] = "Required field '{$required}' is not mapped from any CSV column.";
            }
        }

        // Check for invalid target fields
        foreach ($field_mapping as $csv_field => $target) {
            if (!empty($target) && !isset($available_targets[$target])) {
                $errors[] = "Invalid target field '{$target}' for CSV field '{$csv_field}'.";
            }
        }

        // Check for unmapped fields (warnings)
        $unmapped_fields = array_diff($detected_fields, array_keys($field_mapping));
        if (!empty($unmapped_fields)) {
            $warnings[] = "The following CSV fields are not mapped: " . implode(', ', $unmapped_fields);
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'is_valid' => empty($errors),
        ];
    }

    /**
     * Apply field mapping with support for multiple fields and priority
     */
    private function applyFieldMapping(array $entry, array $field_mapping, array $field_priorities = []): array
    {
        $mapped = [];
        $field_groups = [];

        // Group fields by target with priority information
        foreach ($field_mapping as $csv_field => $target) {
            if (!empty($target)) {
                if (!isset($field_groups[$target])) {
                    $field_groups[$target] = [];
                }
                $priority = $field_priorities[$csv_field] ?? 10;
                $field_groups[$target][] = [
                    'field' => $csv_field,
                    'priority' => $priority,
                    'value' => $entry[$csv_field] ?? ''
                ];
            }
        }

        // Process each target field
        foreach ($field_groups as $target => $field_data) {
            // Sort by priority (lower number = higher priority)
            usort($field_data, function ($a, $b) {
                return $a['priority'] <=> $b['priority'];
            });

            $values = [];
            $non_empty_values = [];

            // Collect all non-empty values for this target
            foreach ($field_data as $data) {
                $value = trim($data['value']);
                if (!empty($value)) {
                    $non_empty_values[] = $value;
                }
                $values[] = $value;
            }

            // Use the first non-empty value (highest priority)
            if (!empty($non_empty_values)) {
                $mapped[$target] = $non_empty_values[0];

                // If multiple non-empty values exist, add alternatives to comment
                if (count($non_empty_values) > 1) {
                    $mapped[$target . '_alternatives'] = array_slice($non_empty_values, 1);
                }
            }
        }

        return $mapped;
    }

    /**
     * Detect available fields in CSV data for field mapping UI
     */
    public function detectFields(string $data, ?string $delimiter = null): array
    {
        if ($delimiter === null) {
            // Detect delimiter by counting occurrences in the first row (header)
            $delimiters = [',', ';', "\t"];
            $lines = explode("\n", $data, 2);
            $header_line = $lines[0] ?? '';
            $delimiter_counts = [];
            foreach ($delimiters as $delim) {
                $delimiter_counts[$delim] = substr_count($header_line, $delim);
            }
            // Choose the delimiter with the highest count, default to comma if all are zero
            $max_count = max($delimiter_counts);
            $delimiter = array_search($max_count, $delimiter_counts, true);
            if ($max_count === 0 || $delimiter === false) {
                $delimiter = ',';
            }
        }
        // Handle potential BOM (Byte Order Mark) at the beginning
        $data = preg_replace('/^\xEF\xBB\xBF/', '', $data);

        // Get first line only for header detection
        $lines = explode("\n", $data);
        $header_line = trim($lines[0] ?? '');


        // Simple manual parsing for header detection
        // This handles quoted CSV fields better than the library for detection
        $fields = [];
        $current_field = '';
        $in_quotes = false;
        $quote_char = '"';

        for ($i = 0; $i < strlen($header_line); $i++) {
            $char = $header_line[$i];

            if ($char === $quote_char && !$in_quotes) {
                $in_quotes = true;
            } elseif ($char === $quote_char && $in_quotes) {
                // Check for escaped quote (double quote)
                if ($i + 1 < strlen($header_line) && $header_line[$i + 1] === $quote_char) {
                    $current_field .= $quote_char;
                    $i++; // Skip next quote
                } else {
                    $in_quotes = false;
                }
            } elseif ($char === $delimiter && !$in_quotes) {
                $fields[] = trim($current_field);
                $current_field = '';
            } else {
                $current_field .= $char;
            }
        }

        // Add the last field
        if ($current_field !== '') {
            $fields[] = trim($current_field);
        }

        // Clean up headers - remove quotes and trim whitespace
        $headers = array_map(function ($header) {
            return trim($header, '"\'');
        }, $fields);


        return array_values($headers);
    }
}
