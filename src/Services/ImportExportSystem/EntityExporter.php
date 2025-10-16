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

use App\Entity\Base\AbstractNamedDBElement;
use App\Entity\Base\AbstractStructuralDBElement;
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
    public function __construct(protected SerializerInterface $serializer)
    {
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

        //Do the serialization with the given options
        $serialized_data = $this->exportEntities($entities, $options);

        $response = new Response($serialized_data);

        //Resolve the format
        $optionsResolver = new OptionsResolver();
        $this->configureOptions($optionsResolver);
        $options = $optionsResolver->resolve($options);

        //Determine the content type for the response

        //Try to use better content types based on the format
        $format = $options['format'];
        $content_type = match ($format) {
            'xml' => 'application/xml',
            'json' => 'application/json',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
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
}
