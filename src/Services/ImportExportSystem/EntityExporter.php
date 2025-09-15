<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Services\ImportExportSystem;

use App\Entity\AssemblySystem\Assembly;
use App\Entity\AssemblySystem\AssemblyBOMEntry;
use App\Entity\Attachments\AttachmentType;
use App\Entity\Base\AbstractNamedDBElement;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\LabelSystem\LabelProfile;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\StorageLocation;
use App\Entity\Parts\Supplier;
use App\Entity\PriceInformations\Currency;
use App\Entity\ProjectSystem\Project;
use App\Helpers\Assemblies\AssemblyPartAggregator;
use App\Helpers\FilenameSanatizer;
use App\Serializer\APIPlatform\SkippableItemNormalizer;
use Symfony\Component\OptionsResolver\OptionsResolver;
use InvalidArgumentException;
use Symfony\Component\Serializer\Exception\CircularReferenceException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use function is_array;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Serializer\SerializerInterface;
use function Symfony\Component\String\u;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

/**
 * Use this class to export an entity to multiple file formats.
 * @see \App\Tests\Services\ImportExportSystem\EntityExporterTest
 */
class EntityExporter
{
    public function __construct(
        protected SerializerInterface    $serializer,
        protected AssemblyPartAggregator $partAggregator, private readonly AssemblyPartAggregator $assemblyPartAggregator,
    ) {
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('format', 'csv');
        $resolver->setAllowedValues('format', ['csv', 'json', 'xml', 'yaml', 'xlsx', 'xls']);

        $resolver->setDefault('csv_delimiter', ';');
        $resolver->setAllowedTypes('csv_delimiter', 'string');

        $resolver->setDefault('level', 'extended');
        $resolver->setAllowedValues('level', ['simple', 'extended', 'full']);

        $resolver->setDefault('include_children', false);
        $resolver->setAllowedTypes('include_children', 'bool');

        $resolver->setDefault('readableSelect', null);
        $resolver->setAllowedValues('readableSelect', [null, 'readable', 'readable_bom']);

    }

    /**
     * Export the given entities using the given options.
     * @param AbstractNamedDBElement|AbstractNamedDBElement[] $entities The data to export
     * @param  array  $options The options to use for exporting
     * @return string The serialized data
     */
    public function exportEntities(AbstractNamedDBElement|array $entities, array $options): string
    {
        if (!is_array($entities)) {
            $entities = [$entities];
        }

        //Ensure that all entities are of type AbstractNamedDBElement
        foreach ($entities as $entity) {
            if (!$entity instanceof AbstractNamedDBElement) {
                throw new InvalidArgumentException('All entities must be of type AbstractNamedDBElement!');
            }
        }

        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);

        $options = $resolver->resolve($options);

        //Handle Excel formats by converting from CSV
        if (in_array($options['format'], ['xlsx', 'xls'], true)) {
            return $this->exportToExcel($entities, $options);
        }

        //If include children is set, then we need to add the include_children group
        $groups = [$options['level']];
        if ($options['include_children']) {
            $groups[] = 'include_children';
        }

        return $this->serializer->serialize(
            $entities,
            $options['format'],
            [
                'groups' => $groups,
                'as_collection' => true,
                'csv_delimiter' => $options['csv_delimiter'],
                'xml_root_node_name' => 'PartDBExport',
                'partdb_export' => true,
                    //Skip the item normalizer, so that we dont get IRIs in the output
                SkippableItemNormalizer::DISABLE_ITEM_NORMALIZER => true,
                    //Handle circular references
                AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => $this->handleCircularReference(...),
            ]
        );
    }

    private function handleCircularReference(object $object): string
    {
        if ($object instanceof AbstractStructuralDBElement) {
            return $object->getFullPath("->");
        } elseif ($object instanceof AbstractNamedDBElement) {
            return $object->getName();
        } elseif ($object instanceof \Stringable) {
            return $object->__toString();
        }

        throw new CircularReferenceException('Circular reference detected for object of type ' . get_class($object));
    }

    /**
     * Exports entities to Excel format (xlsx or xls).
     *
     * @param AbstractNamedDBElement[] $entities The entities to export
     * @param array                    $options  The export options
     *
     * @return string The Excel file content as binary string
     */
    protected function exportToExcel(array $entities, array $options): string
    {
        //First get CSV data using existing serializer
        $groups = [$options['level']];
        if ($options['include_children']) {
            $groups[] = 'include_children';
        }

        $csvData = $this->serializer->serialize(
            $entities,
            'csv',
            [
                'groups' => $groups,
                'as_collection' => true,
                'csv_delimiter' => $options['csv_delimiter'],
                'partdb_export' => true,
                SkippableItemNormalizer::DISABLE_ITEM_NORMALIZER => true,
                AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => $this->handleCircularReference(...),
            ]
        );

        //Convert CSV to Excel
        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();

        $rows = explode("\n", $csvData);
        $rowIndex = 1;

        foreach ($rows as $row) {
            if (trim($row) === '') {
                continue;
            }

            $columns = str_getcsv($row, $options['csv_delimiter'], '"', '\\');
            $colIndex = 1;

            foreach ($columns as $column) {
                $cellCoordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex) . $rowIndex;
                $worksheet->setCellValue($cellCoordinate, $column);
                $colIndex++;
            }
            $rowIndex++;
        }

        //Save to memory stream
        $writer = $options['format'] === 'xlsx' ? new Xlsx($spreadsheet) : new Xls($spreadsheet);

        $memFile = fopen("php://temp", 'r+b');
        $writer->save($memFile);
        rewind($memFile);
        $content = stream_get_contents($memFile);
        fclose($memFile);

        if ($content === false) {
            throw new \RuntimeException('Failed to read Excel content from memory stream.');
        }

        return $content;
    }

    /**
     * Exports an Entity or an array of entities to multiple file formats.
     *
     * @param Request                         $request the request that should be used for option resolving
     * @param AbstractNamedDBElement|object[] $entities
     *
     * @return Response the generated response containing the exported data
     *
     * @throws ReflectionException
     */
    public function exportEntityFromRequest(AbstractNamedDBElement|array $entities, Request $request): Response
    {
        $options = [
            'format' => $request->get('format') ?? 'json',
            'level' => $request->get('level') ?? 'extended',
            'include_children' => $request->request->getBoolean('include_children'),
        ];

        if (!is_array($entities)) {
            $entities = [$entities];
        }

        if ($request->get('readableSelect', false) === 'readable') {
            // Map entity classes to export functions
            $entityExportMap = [
                AttachmentType::class => fn($entities) => $this->exportReadable($entities, AttachmentType::class),
                Category::class => fn($entities) => $this->exportReadable($entities, Category::class),
                Project::class => fn($entities) => $this->exportReadable($entities, Project::class),
                Assembly::class => fn($entities) => $this->exportReadable($entities, Assembly::class),
                Supplier::class => fn($entities) => $this->exportReadable($entities, Supplier::class),
                Manufacturer::class => fn($entities) => $this->exportReadable($entities, Manufacturer::class),
                StorageLocation::class => fn($entities) => $this->exportReadable($entities, StorageLocation::class),
                Footprint::class => fn($entities) => $this->exportReadable($entities, Footprint::class),
                Currency::class => fn($entities) => $this->exportReadable($entities, Currency::class),
                MeasurementUnit::class => fn($entities) => $this->exportReadable($entities, MeasurementUnit::class),
                LabelProfile::class => fn($entities) => $this->exportReadable($entities, LabelProfile::class, false),
            ];

            // Determine the type of the entity
            $type = null;
            foreach ($entities as $entity) {
                $entityClass = get_class($entity);
                if (isset($entityExportMap[$entityClass])) {
                    $type = $entityClass;
                    break;
                }
            }

            // Generate the response
            $response = isset($entityExportMap[$type])
                ? new Response($entityExportMap[$type]($entities))
                : new Response('');

            $options['format'] = 'csv';
            $options['level'] = 'readable';
        } if ($request->get('readableSelect', false) === 'readable_bom') {
            $hierarchies = [];

            foreach ($entities as $entity) {
                if (!$entity instanceof Assembly) {
                    throw new InvalidArgumentException('Only assemblies can be exported in readable BOM format');
                }

                $hierarchies[] = $this->assemblyPartAggregator->processAssemblyHierarchyForPdf($entity, 0, 1, 1);
            }

            $pdfContent = $this->assemblyPartAggregator->exportReadableHierarchyForPdf($hierarchies);

            $response = new Response($pdfContent);

            $options['format'] = 'pdf';
            $options['level'] = 'readable_bom';
        } else {
            //Do the serialization with the given options
            $serialized_data = $this->exportEntities($entities, $options);

            $response = new Response($serialized_data);

            //Resolve the format
            $optionsResolver = new OptionsResolver();
            $this->configureOptions($optionsResolver);
            $options = $optionsResolver->resolve($options);
        }

        //Determine the content type for the response

        //Try to use better content types based on the format
        $format = $options['format'];
        $content_type = match ($format) {
            'xml' => 'application/xml',
            'json' => 'application/json',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
            'pdf' => 'application/pdf',
            default => 'text/plain',
        };
        $response->headers->set('Content-Type', $content_type);

        //If view option is not specified, then download the file.
        if (!$request->get('view')) {

            //Determine the filename
            //When we only have one entity, then we can use the name of the entity
            if (count($entities) === 1) {
                $entity_name = $entities[0]->getName();
            } else {
                //Use the class name of the first element for the filename otherwise
                $reflection = new ReflectionClass($entities[0]);
                $entity_name = $reflection->getShortName();
            }

            $level = $options['level'];

            $filename = "export_{$entity_name}_{$level}.{$format}";

            //Sanitize the filename
            $filename = FilenameSanatizer::sanitizeFilename($filename);

            // Create the disposition of the file
            $disposition = $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $filename,
                u($filename)->ascii()->toString(),
            );
            // Set the content disposition
            $response->headers->set('Content-Disposition', $disposition);
        }

        return $response;
    }

    /**
     * Exports data for multiple entity types in a readable CSV format.
     *
     * @param array $entities The entities to export.
     * @param string $type The type of entities ('category', 'project', 'assembly', 'attachmentType', 'supplier').
     * @return string The generated CSV content as a string.
     */
    public function exportReadable(array $entities, string $type, bool $isHierarchical = true): string
    {
        //Define headers and entity-specific processing logic
        $defaultProcessEntity = fn($entity, $depth) => [
            'Id' => $entity->getId(),
            'ParentId' => $entity->getParent()?->getId() ?? '',
            'NameHierarchical' => str_repeat('--', $depth) . ' ' . $entity->getName(),
            'Name' => $entity->getName(),
            'FullName' => $this->getFullName($entity),
        ];

        $config = [
            AttachmentType::class => [
                'header' => ['Id', 'ParentId', 'NameHierarchical', 'Name', 'FullName'],
                'processEntity' => $defaultProcessEntity,
            ],
            Category::class => [
                'header' => ['Id', 'ParentId', 'NameHierarchical', 'Name', 'FullName'],
                'processEntity' => $defaultProcessEntity,
            ],
            Project::class => [
                'header' => [
                    'Id', 'ParentId', 'Type', 'ProjectNameHierarchical', 'ProjectName', 'ProjectFullName', 'BomQuantity',
                    'BomPartId', 'BomPartIpn', 'BomPartMpnr', 'BomPartName', 'BomDesignator', 'BomPartDescription',
                    'BomMountNames'
                ],
                'processEntity' => fn($entity, $depth) => [
                    'ProjectId' => $entity->getId(),
                    'ParentProjectId' => $entity->getParent()?->getId() ?? '',
                    'Type' => 'project',
                    'ProjectNameHierarchical' => str_repeat('--', $depth) . ' ' . $entity->getName(),
                    'ProjectName' => $entity->getName(),
                    'ProjectFullName' => $this->getFullName($entity),
                    'BomQuantity' => '-',
                    'BomPartId' => '-',
                    'BomPartIpn' => '-',
                    'BomPartMpnr' => '-',
                    'BomPartName' => '-',
                    'BomDesignator' => '-',
                    'BomPartDescription' => '-',
                    'BomMountNames' => '-',
                ],
                'processBomEntries' => fn($entity, $depth) => array_map(fn(AssemblyBOMEntry $bomEntry) => [
                    'Id' => $entity->getId(),
                    'ParentId' => '',
                    'Type' => 'project_bom_entry',
                    'ProjectNameHierarchical' => str_repeat('--', $depth) . '> ' . $entity->getName(),
                    'ProjectName' => $entity->getName(),
                    'ProjectFullName' => $this->getFullName($entity),
                    'BomQuantity' => $bomEntry->getQuantity() ?? '',
                    'BomPartId' => $bomEntry->getPart()?->getId() ?? '',
                    'BomPartIpn' => $bomEntry->getPart()?->getIpn() ?? '',
                    'BomPartMpnr' => $bomEntry->getPart()?->getManufacturerProductNumber() ?? '',
                    'BomPartName' => $bomEntry->getPart()?->getName() ?? '',
                    'BomDesignator' => $bomEntry->getName() ?? '',
                    'BomPartDescription' => $bomEntry->getPart()?->getDescription() ?? '',
                    'BomMountNames' => $bomEntry->getMountNames(),
                ], $entity->getBomEntries()->toArray()),
            ],
            Assembly::class => [
                'header' => [
                    'Id', 'ParentId', 'Type', 'AssemblyIpn', 'AssemblyNameHierarchical', 'AssemblyName',
                    'AssemblyFullName', 'BomQuantity', 'BomMultiplier', 'BomPartId', 'BomPartIpn', 'BomPartMpnr',
                    'BomPartName', 'BomDesignator', 'BomPartDescription', 'BomMountNames', 'BomReferencedAssemblyId',
                    'BomReferencedAssemblyIpn', 'BomReferencedAssemblyFullName'
                ],
                'processEntity' => fn($entity, $depth) => [
                    'Id' => $entity->getId(),
                    'ParentId' => $entity->getParent()?->getId() ?? '',
                    'Type' => 'assembly',
                    'AssemblyIpn' => $entity->getIpn(),
                    'AssemblyNameHierarchical' => str_repeat('--', $depth) . ' ' . $entity->getName(),
                    'AssemblyName' => $entity->getName(),
                    'AssemblyFullName' => $this->getFullName($entity),
                    'BomQuantity' => '-',
                    'BomMultiplier' => '-',
                    'BomPartId' => '-',
                    'BomPartIpn' => '-',
                    'BomPartMpnr' => '-',
                    'BomPartName' => '-',
                    'BomDesignator' => '-',
                    'BomPartDescription' => '-',
                    'BomMountNames' => '-',
                    'BomReferencedAssemblyId' => '-',
                    'BomReferencedAssemblyIpn' => '-',
                    'BomReferencedAssemblyFullName' => '-',
                ],
                'processBomEntries' => fn($entity, $depth) => $this->processBomEntriesWithAggregatedParts($entity, $depth),
            ],
            Supplier::class => [
                'header' => ['Id', 'ParentId', 'NameHierarchical', 'Name', 'FullName'],
                'processEntity' => $defaultProcessEntity,
            ],
            Manufacturer::class => [
                'header' => ['Id', 'ParentId', 'NameHierarchical', 'Name', 'FullName'],
                'processEntity' => $defaultProcessEntity,
            ],
            StorageLocation::class => [
                'header' => ['Id', 'ParentId', 'NameHierarchical', 'Name', 'FullName'],
                'processEntity' => $defaultProcessEntity,
            ],
            Footprint::class => [
                'header' => ['Id', 'ParentId', 'NameHierarchical', 'Name', 'FullName'],
                'processEntity' => $defaultProcessEntity,
            ],
            Currency::class => [
                'header' => ['Id', 'ParentId', 'NameHierarchical', 'Name', 'FullName'],
                'processEntity' => $defaultProcessEntity,
            ],
            MeasurementUnit::class => [
                'header' => ['Id', 'ParentId', 'NameHierarchical', 'Name', 'FullName'],
                'processEntity' => $defaultProcessEntity,
            ],
            LabelProfile::class => [
                'header' => ['Id', 'SupportedElement', 'Name'],
                'processEntity' => fn(LabelProfile $entity, $depth) => [
                    'Id' => $entity->getId(),
                    'SupportedElement' => $entity->getOptions()->getSupportedElement()->name,
                    'Name' => $entity->getName(),
                ],
            ],
        ];

        //Get configuration for the entity type
        $entityConfig = $config[$type] ?? null;

        if (!$entityConfig) {
            return '';
        }

        //Initialize CSV data with the header
        $csvData = [];
        $csvData[] = $entityConfig['header'];

        $relevantEntities = $entities;

        if ($isHierarchical) {
            //Filter root entities (those without parents)
            $relevantEntities = array_filter($entities, fn($entity) => $entity->getParent() === null);

            if (count($relevantEntities) === 0 && count($entities) > 0) {
                //If no root entities are found, then we need to add all entities

                $relevantEntities = $entities;
            }
        }

        //Sort root entities alphabetically by `name`
        usort($relevantEntities, fn($a, $b) => strnatcasecmp($a->getName(), $b->getName()));

        //Recursive function to process an entity and its children
        $processEntity = function ($entity, &$csvData, $depth = 0) use (&$processEntity, $entityConfig, $isHierarchical) {
            //Add main entity data to CSV
            $csvData[] = $entityConfig['processEntity']($entity, $depth);

            //Process BOM entries if applicable
            if (isset($entityConfig['processBomEntries'])) {
                $bomRows = $entityConfig['processBomEntries']($entity, $depth);
                foreach ($bomRows as $bomRow) {
                    $csvData[] = $bomRow;
                }
            }

            if ($isHierarchical) {
                //Retrieve children, sort alphabetically, then process them
                $children = $entity->getChildren()->toArray();
                usort($children, fn($a, $b) => strnatcasecmp($a->getName(), $b->getName()));
                foreach ($children as $childEntity) {
                    $processEntity($childEntity, $csvData, $depth + 1);
                }
            }
        };

        //Start processing with root entities
        foreach ($relevantEntities as $rootEntity) {
            $processEntity($rootEntity, $csvData);
        }

        //Generate CSV string
        $output = '';
        foreach ($csvData as $line) {
            $output .= implode(';', $line) . "\n"; // Use a semicolon as the delimiter
        }

        return $output;
    }

    /**
     * Process BOM entries and include aggregated parts as "complete_part_list".
     *
     * @param Assembly $assembly The assembly being processed.
     * @param int $depth The current depth in the hierarchy.
     * @return array Processed BOM entries and aggregated parts rows.
     */
    private function processBomEntriesWithAggregatedParts(Assembly $assembly, int $depth): array
    {
        $rows = [];

        foreach ($assembly->getBomEntries() as $bomEntry) {
            // Add the BOM entry itself
            $rows[] = [
                'Id' => $assembly->getId(),
                'ParentId' => '',
                'Type' => 'assembly_bom_entry',
                'AssemblyIpn' => $assembly->getIpn(),
                'AssemblyNameHierarchical' => str_repeat('--', $depth) . '> ' . $assembly->getName(),
                'AssemblyName' => $assembly->getName(),
                'AssemblyFullName' => $this->getFullName($assembly),
                'BomQuantity' => $bomEntry->getQuantity() ?? '',
                'BomMultiplier' => '',
                'BomPartId' => $bomEntry->getPart()?->getId() ?? '-',
                'BomPartIpn' => $bomEntry->getPart()?->getIpn() ?? '-',
                'BomPartMpnr' => $bomEntry->getPart()?->getManufacturerProductNumber() ?? '-',
                'BomPartName' => $bomEntry->getPart()?->getName() ?? '-',
                'BomDesignator' => $bomEntry->getName() ?? '-',
                'BomPartDescription' => $bomEntry->getPart()?->getDescription() ?? '-',
                'BomMountNames' => $bomEntry->getMountNames(),
                'BomReferencedAssemblyId' => $bomEntry->getReferencedAssembly()?->getId() ?? '-',
                'BomReferencedAssemblyIpn' => $bomEntry->getReferencedAssembly()?->getIpn() ?? '-',
                'BomReferencedAssemblyFullName' => $this->getFullName($bomEntry->getReferencedAssembly() ?? null),
            ];

            // If a referenced assembly exists, add aggregated parts
            if ($bomEntry->getReferencedAssembly() instanceof Assembly) {
                $referencedAssembly = $bomEntry->getReferencedAssembly();

                // Get aggregated parts for the referenced assembly
                $aggregatedParts = $this->assemblyPartAggregator->getAggregatedParts($referencedAssembly, $bomEntry->getQuantity());;

                foreach ($aggregatedParts as $partData) {
                    $partAssembly = $partData['assembly'] ?? null;

                    $rows[] = [
                        'Id' => $assembly->getId(),
                        'ParentId' => '',
                        'Type' => 'subassembly_part_list',
                        'AssemblyIpn' => $partAssembly ? $partAssembly->getIpn() : '',
                        'AssemblyNameHierarchical' => '',
                        'AssemblyName' => $partAssembly ? $partAssembly->getName() : '',
                        'AssemblyFullName' => $this->getFullName($partAssembly),
                        'BomQuantity' => $partData['quantity'],
                        'BomMultiplier' => $partData['multiplier'],
                        'BomPartId' => $partData['part']?->getId(),
                        'BomPartIpn' => $partData['part']?->getIpn(),
                        'BomPartMpnr' => $partData['part']?->getManufacturerProductNumber(),
                        'BomPartName' => $partData['part']?->getName(),
                        'BomDesignator' => $partData['part']?->getName(),
                        'BomPartDescription' => $partData['part']?->getDescription(),
                        'BomMountNames' => '-',
                        'BomReferencedAssemblyId' => '-',
                        'BomReferencedAssemblyIpn' => '-',
                        'BomReferencedAssemblyFullName' => '-',
                    ];
                }
            }
        }

        return $rows;
    }

    /**
     * Constructs the full hierarchical name of an object by traversing
     * through its parent objects and concatenating their names using
     * a specified separator.
     *
     * @param AttachmentType|Category|Project|Assembly|Supplier|Manufacturer|StorageLocation|Footprint|Currency|MeasurementUnit|LabelProfile|null $object The object whose full name is to be constructed. If null, the result will be an empty string.
     * @param string $separator The string used to separate the names of the objects in the full hierarchy.
     *
     * @return string The full hierarchical name constructed by concatenating the names of the object and its parents.
     */
    private function getFullName(AttachmentType|Category|Project|Assembly|Supplier|Manufacturer|StorageLocation|Footprint|Currency|MeasurementUnit|LabelProfile|null $object, string $separator = '->'): string
    {
        $fullNameParts = [];

        while ($object !== null) {
            array_unshift($fullNameParts, $object->getName());
            $object = $object->getParent();
        }

        return implode($separator, $fullNameParts);
    }
}
