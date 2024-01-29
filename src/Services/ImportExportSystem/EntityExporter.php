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
use App\Helpers\FilenameSanatizer;
use Symfony\Component\OptionsResolver\OptionsResolver;
use InvalidArgumentException;
use function is_array;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Serializer\SerializerInterface;
use function Symfony\Component\String\u;

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
        $resolver->setAllowedValues('format', ['csv', 'json', 'xml', 'yaml']);

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

        //If include children is set, then we need to add the include_children group
        $groups = [$options['level']];
        if ($options['include_children']) {
            $groups[] = 'include_children';
        }

        return $this->serializer->serialize($entities, $options['format'],
            [
                'groups' => $groups,
                'as_collection' => true,
                'csv_delimiter' => $options['csv_delimiter'],
                'xml_root_node_name' => 'PartDBExport',
                'partdb_export' => true,
            ]
        );
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
            'include_children' => $request->request->getBoolean('include_children') ?? false,
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

        //Plain text should work for all types
        $content_type = 'text/plain';

        //Try to use better content types based on the format
        $format = $options['format'];
        switch ($format) {
            case 'xml':
                $content_type = 'application/xml';
                break;
            case 'json':
                $content_type = 'application/json';
                break;
        }
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

            $filename = 'export_'.$entity_name.'_'.$level.'.'.$format;

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
