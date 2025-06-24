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

use App\Entity\AssemblySystem\Assembly;
use App\Entity\AssemblySystem\AssemblyBOMEntry;
use App\Entity\Parts\Category;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\Part;
use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Repository\DBElementRepository;
use App\Repository\PartRepository;
use App\Repository\Parts\CategoryRepository;
use App\Repository\Parts\ManufacturerRepository;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use League\Csv\Reader;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use UnexpectedValueException;
use Symfony\Component\Validator\ConstraintViolation;

/**
 * @see \App\Tests\Services\ImportExportSystem\BOMImporterTest
 */
class BOMImporter
{

    private const IMPORT_TYPE_JSON = 'json';
    private const IMPORT_TYPE_CSV = 'csv';
    private const IMPORT_TYPE_KICAD_PCB = 'kicad_pcbnew';
    private const IMPORT_TYPE_KICAD_SCHEMATIC = 'kicad_schematic';

    private const MAP_KICAD_PCB_FIELDS = [
        0 => 'Id',
        1 => 'Designator',
        2 => 'Package',
        3 => 'Quantity',
        4 => 'Designation',
        5 => 'Supplier and ref',
    ];

    private string $jsonRoot = '';

    private PartRepository $partRepository;

    private ManufacturerRepository $manufacturerRepository;

    private CategoryRepository $categoryRepository;

    private DBElementRepository $projectBOMEntryRepository;

    private DBElementRepository $assemblyBOMEntryRepository;

    private TranslatorInterface $translator;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
        private readonly BOMValidationService $validationService
    ) {
        $this->partRepository = $entityManager->getRepository(Part::class);
        $this->manufacturerRepository = $entityManager->getRepository(Manufacturer::class);
        $this->categoryRepository = $entityManager->getRepository(Category::class);
        $this->projectBOMEntryRepository = $entityManager->getRepository(ProjectBOMEntry::class);
        $this->assemblyBOMEntryRepository = $entityManager->getRepository(AssemblyBOMEntry::class);
        $this->translator = $translator;
    }

    protected function configureOptions(OptionsResolver $resolver): OptionsResolver
    {
        $resolver->setRequired('type');
        $resolver->setAllowedValues('type', [self::IMPORT_TYPE_KICAD_PCB, self::IMPORT_TYPE_KICAD_SCHEMATIC, self::IMPORT_TYPE_JSON, self::IMPORT_TYPE_CSV]);

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
     */
    public function importFileIntoProject(UploadedFile $file, Project $project, array $options): ImporterResult
    {
        $importerResult = $this->fileToImporterResult($file, $options);

        if ($importerResult->getViolations()->count() === 0) {
            //Assign the bom_entries to the project
            foreach ($importerResult->getBomEntries() as $bomEntry) {
                $project->addBomEntry($bomEntry);
            }
        }

        return $importerResult;
    }

    /**
     * Converts the given file into an ImporterResult with an array of BOM entries using the given options and save them into the given assembly.
     * The changes are not saved into the database yet.
     */
    public function importFileIntoAssembly(UploadedFile $file, Assembly $assembly, array $options): ImporterResult
    {
        $importerResult = $this->fileToImporterResult($file, $options, AssemblyBOMEntry::class);

        if ($importerResult->getViolations()->count() === 0) {
            //Assign the bom_entries to the assembly
            foreach ($importerResult->getBomEntries() as $bomEntry) {
                $assembly->addBomEntry($bomEntry);
            }
        }

        return $importerResult;
    }

    /**
     * Converts the given file into an array of BOM entries using the given options.
     * @return ProjectBOMEntry[]|AssemblyBOMEntry[]
     */
    public function fileToBOMEntries(File $file, array $options, string $objectType = ProjectBOMEntry::class): array
    {
        return $this->stringToBOMEntries($file->getContent(), $options, $objectType);
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
     * Converts the given file into an ImporterResult with an array of BOM entries using the given options.
     */
    public function fileToImporterResult(UploadedFile $file, array $options, string $objectType = ProjectBOMEntry::class): ImporterResult
    {
        $result = new ImporterResult();

        //Available file endings depending on the import type
        $validExtensions = match ($options['type']) {
            self::IMPORT_TYPE_KICAD_PCB => ['kicad_pcb'],
            self::IMPORT_TYPE_JSON => ['json'],
            self::IMPORT_TYPE_CSV => ['csv'],
            default => [],
        };

        //Get the file extension of the uploaded file
        $fileExtension = pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION);

        //Check whether the file extension is valid
        if ($validExtensions === []) {
            $result->addViolation($this->buildJsonViolation(
                'validator.bom_importer.invalid_import_type',
                'import.type'
            ));

            return $result;
        } else if (!in_array(strtolower($fileExtension), $validExtensions, true)) {
            $result->addViolation($this->buildJsonViolation(
                'validator.bom_importer.invalid_file_extension',
                'file.extension',
                $fileExtension,
                [
                    '%extension%' => $fileExtension,
                    '%importType%' => $this->translator->trans($objectType === ProjectBOMEntry::class ? 'project.bom_import.type.'.$options['type'] : 'assembly.bom_import.type.'.$options['type']),
                    '%allowedExtensions%' => implode(', ', $validExtensions),
                ]
            ));

            return $result;
        }

        return $this->stringToImporterResult($file->getContent(), $options, $objectType);
    }

    /**
     * Import string data into an array of BOM entries, which are not yet assigned to a project.
     * @param  string  $data The data to import
     * @param  array  $options An array of options
     * @return ProjectBOMEntry[]|AssemblyBOMEntry[] An array of imported entries
     */
    public function stringToBOMEntries(string $data, array $options, string $objectType = ProjectBOMEntry::class): array
    {
        $resolver = new OptionsResolver();
        $resolver = $this->configureOptions($resolver);
        $options = $resolver->resolve($options);

        return match ($options['type']) {
            self::IMPORT_TYPE_KICAD_PCB => $this->parseKiCADPCB($data, $objectType)->getBomEntries(),
            default => throw new InvalidArgumentException($this->translator->trans('validator.bom_importer.invalid_import_type', [], 'validators')),
        };
    }

    /**
     * Import string data into an array of BOM entries, which are not yet assigned to a project.
     * @param  string  $data The data to import
     * @param  array  $options An array of options
     * @return ImporterResult An result of imported entries or a violation list
     */
    public function stringToImporterResult(string $data, array $options, string $objectType = ProjectBOMEntry::class): ImporterResult
    {
        $resolver = new OptionsResolver();
        $resolver = $this->configureOptions($resolver);
        $options = $resolver->resolve($options);

        $defaultImporterResult = new ImporterResult();
        $defaultImporterResult->addViolation($this->buildJsonViolation(
            'validator.bom_importer.invalid_import_type',
            'import.type'
        ));

        return match ($options['type']) {
            self::IMPORT_TYPE_KICAD_PCB => $this->parseKiCADPCB($data, $objectType),
            self::IMPORT_TYPE_JSON => $this->parseJson($data, $objectType),
            self::IMPORT_TYPE_CSV => $this->parseCsv($data, $objectType),
            default => $defaultImporterResult,
        };
    }

    private function parseKiCADPCB(string $data, string $objectType = ProjectBOMEntry::class): ImporterResult
    {
        $result = new ImporterResult();

        $csv = Reader::createFromString($data);
        $csv->setDelimiter(';');
        $csv->setHeaderOffset(0);

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

            $bom_entry = $objectType === ProjectBOMEntry::class ? new ProjectBOMEntry() : new AssemblyBOMEntry();
            if ($objectType === ProjectBOMEntry::class) {
                $bom_entry->setName($entry['Designation'] . ' (' . $entry['Package'] . ')');
            } else {
                $bom_entry->setName($entry['Designation']);
            }

            $bom_entry->setMountnames($entry['Designator']);
            $bom_entry->setComment($entry['Supplier and ref'] ?? '');
            $bom_entry->setQuantity((float) ($entry['Quantity'] ?? 1));

            $result->addBomEntry($bom_entry);
        }

        return $result;
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
     * Parses the given JSON data into an ImporterResult while validating and transforming entries according to the
     * specified options and object type. If violations are encountered during parsing, they are added to the result.
     *
     * The structure of each entry in the JSON data is validated to ensure that required fields (e.g., quantity, and name)
     * are present, and optional composite fields, like `part` and its sub-properties, meet specific criteria. Various
     * conditions are checked, including whether the provided values are the correct types, and if relationships (like
     * matching parts or manufacturers) are resolved successfully.
     *
     * Violations are added for:
     * - Missing or invalid `quantity` values.
     * - Non-string `name` values.
     * - Invalid structure or missing sub-properties in `part`.
     * - Incorrect or unresolved references to parts and their information, such as `id`, `name`, `manufacturer_product_number`
     *   (mpnr), `internal_part_number` (ipn), or `description`.
     * - Inconsistent or absent manufacturer information.
     *
     * If a match for a part or manufacturer cannot be resolved, a violation is added alongside an indication of the
     * imported value and any partially matched information. Warnings for no exact matches are also added for parts
     * using specific identifying properties like name, manufacturer product number, or internal part numbers.
     *
     * Additional validations include:
     * - Checking for empty or invalid descriptions.
     * - Ensuring manufacturers, if specified, have valid `name` or `id` values.
     *
     * @param string $data JSON encoded string containing BOM entries data.
     * @param string $objectType The type of entries expected during import (e.g., `ProjectBOMEntry` or `AssemblyBOMEntry`).
     *
     * @return ImporterResult The result containing parsed data and any violations encountered during the parsing process.
     */
    private function parseJson(string $data, array $options = [], string $objectType = ProjectBOMEntry::class): ImporterResult
    {
        $result = new ImporterResult();
        $this->jsonRoot = 'JSON Import for '.$objectType === ProjectBOMEntry::class ? 'Project' : 'Assembly';

        $data = json_decode($data, true);

        foreach ($data as $key => $entry) {
            if (!isset($entry['quantity'])) {
                $result->addViolation($this->buildJsonViolation(
                    'validator.bom_importer.json_csv.quantity.required',
                    "entry[$key].quantity"
                ));
            }

            if (isset($entry['quantity']) && (!is_float($entry['quantity']) || $entry['quantity'] <= 0)) {
                $result->addViolation($this->buildJsonViolation(
                    'validator.bom_importer.json_csv.quantity.float',
                    "entry[$key].quantity",
                    $entry['quantity']
                ));
            }

            if (isset($entry['name']) && !is_string($entry['name'])) {
                $result->addViolation($this->buildJsonViolation(
                    'validator.bom_importer.json_csv.parameter.string.notEmpty',
                    "entry[$key].name",
                    $entry['name']
                ));
            }

            if (isset($entry['part'])) {
                $this->processPart($entry, $result, $key, $objectType,self::IMPORT_TYPE_JSON);
            } else {
                $bomEntry = $this->getOrCreateBomEntry($objectType, $entry['name'] ?? null);
                $bomEntry->setQuantity((float) $entry['quantity']);

                $result->addBomEntry($bomEntry);
            }
        }

        return $result;
    }


    /**
     * Parses a CSV string and processes its rows into hierarchical data structures,
     * performing validations and converting data based on the provided headers.
     * Handles potential violations and manages the creation of BOM entries based on the given type.
     *
     * @param string $csvData The raw CSV data to parse, with rows separated by newlines.
     * @param string $objectType The class type to instantiate for BOM entries, defaults to ProjectBOMEntry.
     *
     * @return ImporterResult Returns an ImporterResult instance containing BOM entries and any validation violations encountered.
     */
    function parseCsv(string $csvData, string $objectType = ProjectBOMEntry::class): ImporterResult
    {
        $result = new ImporterResult();
        $rows = explode("\n", trim($csvData));
        $headers = str_getcsv(array_shift($rows), ';');

        foreach ($rows as $key => $row) {
            $entry = [];
            $values = str_getcsv($row, ';');

            foreach ($headers as $index => $column) {
                //Convert column name into hierarchy
                $path = explode('_', $column);
                $temp = &$entry;

                foreach ($path as $step) {
                    if (!isset($temp[$step])) {
                        $temp[$step] = [];
                    }

                    $temp = &$temp[$step];
                }

                //If there is no value, skip
                if (isset($values[$index]) && $values[$index] !== '') {
                    //Check whether the value is numerical
                    if (is_numeric($values[$index])) {
                        //Convert to integer or float
                        $temp = (strpos($values[$index], '.') !== false)
                            ? floatval($values[$index])
                            : intval($values[$index]);
                    } else {
                        //Leave other data types untouched
                        $temp = $values[$index];
                    }
                }
            }

            $entry = $this->removeEmptyProperties($entry);

            if (!isset($entry['quantity'])) {
                $result->addViolation($this->buildJsonViolation(
                    'validator.bom_importer.csv.quantity.required',
                    "row[$key].quantity"
                ));
            }

            if (isset($entry['quantity']) && (!is_numeric($entry['quantity']) || $entry['quantity'] <= 0)) {
                $result->addViolation($this->buildJsonViolation(
                    'validator.bom_importer.csv.quantity.float',
                    "row[$key].quantity",
                    $entry['quantity']
                ));
            }

            if (isset($entry['name']) && !is_string($entry['name'])) {
                $result->addViolation($this->buildJsonViolation(
                    'validator.bom_importer.csv.parameter.string.notEmpty',
                    "row[$key].name",
                    $entry['name']
                ));
            }

            if (isset($entry['part'])) {
                $this->processPart($entry, $result, $key, $objectType, self::IMPORT_TYPE_CSV);
            } else {
                $bomEntry = $this->getOrCreateBomEntry($objectType, $entry['name'] ?? null);
                $bomEntry->setQuantity((float) $entry['quantity'] ?? 0);

                $result->addBomEntry($bomEntry);
            }
        }

        return $result;
    }

    /**
     * Processes an individual part entry in the import data.
     *
     * This method validates the structure and content of the provided part entry and uses the findings
     * to identify corresponding objects in the database. The result is recorded, and violations are
     * logged if issues or discrepancies exist in the validation or database matching process.
     *
     * @param array $entry The array representation of the part entry.
     * @param ImporterResult $result The result object used for recording validation violations.
     * @param int $key The index of the entry in the data array.
     * @param string $objectType The type of object being processed.
     * @param string $importType The type of import being performed.
     *
     * @return void
     */
    private function processPart(array $entry, ImporterResult $result, int $key, string $objectType, string $importType): void
    {
        $prefix = $importType === self::IMPORT_TYPE_JSON ? 'entry' : 'row';

        if (!is_array($entry['part'])) {
            $result->addViolation($this->buildJsonViolation(
                'validator.bom_importer.json_csv.parameter.array',
                $prefix."[$key].part",
                $entry['part']
            ));
        }

        $partIdValid = isset($entry['part']['id']) && is_int($entry['part']['id']) && $entry['part']['id'] > 0;
        $partMpnrValid = isset($entry['part']['mpnr']) && is_string($entry['part']['mpnr']) && trim($entry['part']['mpnr']) !== '';
        $partIpnValid = isset($entry['part']['ipn']) && is_string($entry['part']['ipn']) && trim($entry['part']['ipn']) !== '';
        $partNameValid = isset($entry['part']['name']) && is_string($entry['part']['name']) && trim($entry['part']['name']) !== '';

        if (!$partIdValid && !$partNameValid && !$partMpnrValid && !$partIpnValid) {
            $result->addViolation($this->buildJsonViolation(
                'validator.bom_importer.json_csv.parameter.subproperties',
                $prefix."[$key].part",
                $entry['part'],
                ['%propertyString%' => '"id", "name", "mpnr", or "ipn"']
            ));
        }

        $part = $partIdValid ? $this->partRepository->findOneBy(['id' => $entry['part']['id']]) : null;
        $part = $part ?? ($partMpnrValid ? $this->partRepository->findOneBy(['manufacturer_product_number' => trim($entry['part']['mpnr'])]) : null);
        $part = $part ?? ($partIpnValid ? $this->partRepository->findOneBy(['ipn' => trim($entry['part']['ipn'])]) : null);
        $part = $part ?? ($partNameValid ? $this->partRepository->findOneBy(['name' => trim($entry['part']['name'])]) : null);

        if ($part === null) {
            $value = sprintf('part.id: %s, part.mpnr: %s, part.ipn: %s, part.name: %s',
                isset($entry['part']['id']) ? '<strong>' . $entry['part']['id'] . '</strong>' : '-',
                isset($entry['part']['mpnr']) ? '<strong>' . $entry['part']['mpnr'] . '</strong>' : '-',
                isset($entry['part']['ipn']) ? '<strong>' . $entry['part']['ipn'] . '</strong>' : '-',
                isset($entry['part']['name']) ? '<strong>' . $entry['part']['name'] . '</strong>' : '-',
            );

            $result->addViolation($this->buildJsonViolation(
                'validator.bom_importer.json_csv.parameter.notFoundFor',
                $prefix."[$key].part",
                $entry['part'],
                ['%value%' => $value]
            ));
        }

        if ($partNameValid && $part !== null && isset($entry['part']['name']) && $part->getName() !== trim($entry['part']['name'])) {
            $result->addViolation($this->buildJsonViolation(
                'validator.bom_importer.json_csv.parameter.noExactMatch',
                $prefix."[$key].part.name",
                $entry['part']['name'],
                [
                    '%importValue%' => '<strong>' . $entry['part']['name'] . '</strong>',
                    '%foundId%' => $part->getID(),
                    '%foundValue%' => '<strong>' . $part->getName() . '</strong>'
                ]
            ));
        }

        if ($partMpnrValid && $part !== null && isset($entry['part']['mpnr']) && $part->getManufacturerProductNumber() !== trim($entry['part']['mpnr'])) {
            $result->addViolation($this->buildJsonViolation(
                'validator.bom_importer.json_csv.parameter.noExactMatch',
                $prefix."[$key].part.mpnr",
                $entry['part']['mpnr'],
                [
                    '%importValue%' => '<strong>' . $entry['part']['mpnr'] . '</strong>',
                    '%foundId%' => $part->getID(),
                    '%foundValue%' => '<strong>' . $part->getManufacturerProductNumber() . '</strong>'
                ]
            ));
        }

        if ($partIpnValid && $part !== null && isset($entry['part']['ipn']) && $part->getIpn() !== trim($entry['part']['ipn'])) {
            $result->addViolation($this->buildJsonViolation(
                'validator.bom_importer.json_csv.parameter.noExactMatch',
                $prefix."[$key].part.ipn",
                $entry['part']['ipn'],
                [
                    '%importValue%' => '<strong>' . $entry['part']['ipn'] . '</strong>',
                    '%foundId%' => $part->getID(),
                    '%foundValue%' => '<strong>' . $part->getIpn() . '</strong>'
                ]
            ));
        }

        if (isset($entry['part']['description'])) {
            if (!is_string($entry['part']['description']) || trim($entry['part']['description']) === '') {
                $result->addViolation($this->buildJsonViolation(
                    'validator.bom_importer.json_csv.parameter.string.notEmpty',
                    'entry[$key].part.description',
                    $entry['part']['description']
                ));
            }
        }

        $partDescription = $entry['part']['description'] ?? '';

        $manufacturerIdValid = false;
        $manufacturerNameValid = false;
        if (array_key_exists('manufacturer', $entry['part'])) {
            if (!is_array($entry['part']['manufacturer'])) {
                $result->addViolation($this->buildJsonViolation(
                    'validator.bom_importer.json_csv.parameter.array',
                    'entry[$key].part.manufacturer',
                    $entry['part']['manufacturer']) ?? null
                );
            }

            $manufacturerIdValid = isset($entry['part']['manufacturer']['id']) && is_int($entry['part']['manufacturer']['id']) && $entry['part']['manufacturer']['id'] > 0;
            $manufacturerNameValid = isset($entry['part']['manufacturer']['name']) && is_string($entry['part']['manufacturer']['name']) && trim($entry['part']['manufacturer']['name']) !== '';

            if (!$manufacturerIdValid && !$manufacturerNameValid) {
                $result->addViolation($this->buildJsonViolation(
                    'validator.bom_importer.json_csv.parameter.manufacturerOrCategoryWithSubProperties',
                    $prefix."[$key].part.manufacturer",
                    $entry['part']['manufacturer'],
                ));
            }
        }

        $manufacturer = $manufacturerIdValid ? $this->manufacturerRepository->findOneBy(['id' => $entry['part']['manufacturer']['id']]) : null;
        $manufacturer = $manufacturer ?? ($manufacturerNameValid ? $this->manufacturerRepository->findOneBy(['name' => trim($entry['part']['manufacturer']['name'])]) : null);

        if (($manufacturerIdValid || $manufacturerNameValid) && $manufacturer === null) {
            $value = sprintf(
                'manufacturer.id: %s, manufacturer.name: %s',
                isset($entry['part']['manufacturer']['id']) && $entry['part']['manufacturer']['id'] !== null ? '<strong>' . $entry['part']['manufacturer']['id'] . '</strong>' : '-',
                isset($entry['part']['manufacturer']['name']) && $entry['part']['manufacturer']['name'] !== null ? '<strong>' . $entry['part']['manufacturer']['name'] . '</strong>' : '-'
            );

            $result->addViolation($this->buildJsonViolation(
                'validator.bom_importer.json_csv.parameter.notFoundFor',
                $prefix."[$key].part.manufacturer",
                $entry['part']['manufacturer'],
                ['%value%' => $value]
            ));
        }

        if ($manufacturerNameValid && $manufacturer !== null && isset($entry['part']['manufacturer']['name']) && $manufacturer->getName() !== trim($entry['part']['manufacturer']['name'])) {
            $result->addViolation($this->buildJsonViolation(
                'validator.bom_importer.json_csv.parameter.noExactMatch',
                $prefix."[$key].part.manufacturer.name",
                $entry['part']['manufacturer']['name'],
                [
                    '%importValue%' => '<strong>' . $entry['part']['manufacturer']['name'] . '</strong>',
                    '%foundId%' => $manufacturer->getID(),
                    '%foundValue%' => '<strong>' . $manufacturer->getName() . '</strong>'
                ]
            ));
        }

        $categoryIdValid = false;
        $categoryNameValid = false;
        if (array_key_exists('category', $entry['part'])) {
            if (!is_array($entry['part']['category'])) {
                $result->addViolation($this->buildJsonViolation(
                    'validator.bom_importer.json_csv.parameter.array',
                    'entry[$key].part.category',
                    $entry['part']['category']) ?? null
                );
            }

            $categoryIdValid = isset($entry['part']['category']['id']) && is_int($entry['part']['category']['id']) && $entry['part']['category']['id'] > 0;
            $categoryNameValid = isset($entry['part']['category']['name']) && is_string($entry['part']['category']['name']) && trim($entry['part']['category']['name']) !== '';

            if (!$categoryIdValid && !$categoryNameValid) {
                $result->addViolation($this->buildJsonViolation(
                    'validator.bom_importer.json_csv.parameter.manufacturerOrCategoryWithSubProperties',
                    $prefix."[$key].part.category",
                    $entry['part']['category']
                ));
            }
        }

        $category = $categoryIdValid ? $this->categoryRepository->findOneBy(['id' => $entry['part']['category']['id']]) : null;
        $category = $category ?? ($categoryNameValid ? $this->categoryRepository->findOneBy(['name' => trim($entry['part']['category']['name'])]) : null);

        if (($categoryIdValid || $categoryNameValid)) {
            $value = sprintf(
                'category.id: %s, category.name: %s',
                isset($entry['part']['category']['id']) && $entry['part']['category']['id'] !== null ? '<strong>' . $entry['part']['category']['id'] . '</strong>' : '-',
                isset($entry['part']['category']['name']) && $entry['part']['category']['name'] !== null ? '<strong>' . $entry['part']['category']['name'] . '</strong>' : '-'
            );

            $result->addViolation($this->buildJsonViolation(
                'validator.bom_importer.json_csv.parameter.notFoundFor',
                $prefix."[$key].part.category",
                $entry['part']['category'],
                ['%value%' => $value]
            ));
        }

        if ($categoryNameValid && $category !== null && isset($entry['part']['category']['name']) && $category->getName() !== trim($entry['part']['category']['name'])) {
            $result->addViolation($this->buildJsonViolation(
                'validator.bom_importer.json_csv.parameter.noExactMatch',
                $prefix."[$key].part.category.name",
                $entry['part']['category']['name'],
                [
                    '%importValue%' => '<strong>' . $entry['part']['category']['name'] . '</strong>',
                    '%foundId%' => $category->getID(),
                    '%foundValue%' => '<strong>' . $category->getName() . '</strong>'
                ]
            ));
        }

        if ($result->getViolations()->count() > 0) {
            return;
        }

        if ($partDescription !== '') {
            //When updating the associated parts to a assembly, take over the description of the part.
            $part->setDescription($partDescription);
        }

        if ($manufacturer !== null && $manufacturer->getID() !== $part->getManufacturer()->getID()) {
            //When updating the associated parts, take over to a assembly of the manufacturer of the part.
            $part->setManufacturer($manufacturer);
        }

        if ($category !== null && $category->getID() !== $part->getCategory()->getID()) {
            //When updating the associated parts to a assembly, take over the category of the part.
            $part->setCategory($category);
        }

        if ($objectType === AssemblyBOMEntry::class) {
            $bomEntry = $this->assemblyBOMEntryRepository->findOneBy(['part' => $part]);

            if ($bomEntry === null) {
                if (isset($entry['name']) && $entry['name'] !== '') {
                    $bomEntry = $this->assemblyBOMEntryRepository->findOneBy(['name' => $entry['name']]);
                }

                if ($bomEntry === null) {
                    $bomEntry = new AssemblyBOMEntry();
                }
            }
        } else {
            $bomEntry = $this->projectBOMEntryRepository->findOneBy(['part' => $part]);

            if ($bomEntry === null) {
                if (isset($entry['name']) && $entry['name'] !== '') {
                    $bomEntry = $this->projectBOMEntryRepository->findOneBy(['name' => $entry['name']]);
                }

                if ($bomEntry === null) {
                    $bomEntry = new ProjectBOMEntry();
                }
            }
        }

        $bomEntry->setQuantity((float) $entry['quantity']);

        if (isset($entry['name'])) {
            $bomEntry->setName(trim($entry['name']) === '' ? null : trim ($entry['name']));
        } else {
            $bomEntry->setName(null);
        }

        $bomEntry->setPart($part);

        $result->addBomEntry($bomEntry);
    }

    private function removeEmptyProperties(array $data): array
    {
        foreach ($data as $key => &$value) {
            //Recursive check when the value is an array
            if (is_array($value)) {
                $value = $this->removeEmptyProperties($value);

                //Remove the array when it is empty after cleaning
                if (empty($value)) {
                    unset($data[$key]);
                }
            } elseif ($value === null || $value === '') {
                //Remove values that are explicitly zero or empty
                unset($data[$key]);
            }
        }

        return $data;
    }

    private function getOrCreateBomEntry(string $objectType, ?string $name)
    {
        $bomEntry = null;

        //Check whether there is a name
        if (!empty($name)) {
            if ($objectType === ProjectBOMEntry::class) {
                $bomEntry = $this->projectBOMEntryRepository->findOneBy(['name' => $name]);
            } elseif ($objectType === AssemblyBOMEntry::class) {
                $bomEntry = $this->assemblyBOMEntryRepository->findOneBy(['name' => $name]);
            }
        }

        //If no bom enttry was found, a new object create
        if ($bomEntry === null) {
            $bomEntry = new $objectType();
        }

        $bomEntry->setName($name);

        return $bomEntry;
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
            $new_index = self::MAP_KICAD_PCB_FIELDS[$index] ?? throw new UnexpectedValueException('Invalid field index!');
            $out[$new_index] = $field;
        }

        return $out;
    }


    /**
     * Builds a JSON-based constraint violation.
     *
     * This method creates a `ConstraintViolation` object that represents a validation error.
     * The violation includes a message, property path, invalid value, and other contextual information.
     * Translations for the violation message can be applied through the translator service.
     *
     * @param string $message The translation key for the validation message.
     * @param string $propertyPath The property path where the violation occurred.
     * @param mixed|null $invalidValue The value that caused the violation (optional).
     * @param array $parameters Additional parameters for message placeholders (default is an empty array).
     *
     * @return ConstraintViolation The created constraint violation object.
     */
    private function buildJsonViolation(string $message, string $propertyPath, mixed $invalidValue = null, array $parameters = []): ConstraintViolation
    {
        return new ConstraintViolation(
            message: $this->translator->trans($message, $parameters, 'validators'),
            messageTemplate: $message,
            parameters: $parameters,
            root: $this->jsonRoot,
            propertyPath: $propertyPath,
            invalidValue: $invalidValue
        );
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
