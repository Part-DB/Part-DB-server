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

use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use InvalidArgumentException;
use League\Csv\Reader;
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

    public function __construct()
    {
    }

    protected function configureOptions(OptionsResolver $resolver): OptionsResolver
    {
        $resolver->setRequired('type');
        $resolver->setAllowedValues('type', ['kicad_pcbnew']);

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
            'kicad_pcbnew' => $this->parseKiCADPCB($data, $options),
            default => throw new InvalidArgumentException('Invalid import type!'),
        };
    }

    private function parseKiCADPCB(string $data, array $options = []): array
    {
        $csv = Reader::createFromString($data);
        $csv->setDelimiter(';');
        $csv->setHeaderOffset(0);

        $bom_entries = [];

        foreach ($csv->getRecords() as $offset => $entry) {
            //Translate the german field names to english
            $entry = $this->normalizeColumnNames($entry);

            //Ensure that the entry has all required fields
            if (!isset ($entry['Designator'])) {
                throw new \UnexpectedValueException('Designator missing at line '.($offset + 1).'!');
            }
            if (!isset ($entry['Package'])) {
                throw new \UnexpectedValueException('Package missing at line '.($offset + 1).'!');
            }
            if (!isset ($entry['Designation'])) {
                throw new \UnexpectedValueException('Designation missing at line '.($offset + 1).'!');
            }
            if (!isset ($entry['Quantity'])) {
                throw new \UnexpectedValueException('Quantity missing at line '.($offset + 1).'!');
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
}
