<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Services;

use App\Entity\Base\AbstractNamedDBElement;
use function in_array;
use InvalidArgumentException;
use function is_array;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Use this class to export an entity to multiple file formats.
 */
class EntityExporter
{
    protected $serializer;

    public function __construct(SerializerInterface $serializer)
    {
        /*$encoders = [new XmlEncoder(), new JsonEncoder(), new CSVEncoder(), new YamlEncoder()];
        $normalizers = [new ObjectNormalizer(), new DateTimeNormalizer()];
        $this->serializer = new Serializer($normalizers, $encoders);
        $this->serializer-> */
        $this->serializer = $serializer;
    }

    /**
     *  Exports an Entity or an array of entities to multiple file formats.
     *
     * @param Request                         $request the request that should be used for option resolving
     * @param AbstractNamedDBElement|object[] $entity
     *
     * @return Response the generated response containing the exported data
     *
     * @throws ReflectionException
     */
    public function exportEntityFromRequest($entity, Request $request): Response
    {
        $format = $request->get('format') ?? 'json';

        //Check if we have one of the supported formats
        if (!in_array($format, ['json', 'csv', 'yaml', 'xml'], true)) {
            throw new InvalidArgumentException('Given format is not supported!');
        }

        //Check export verbosity level
        $level = $request->get('level') ?? 'extended';
        if (!in_array($level, ['simple', 'extended', 'full'], true)) {
            throw new InvalidArgumentException('Given level is not supported!');
        }

        //Check for include children option
        $include_children = $request->get('include_children') ?? false;

        //Check which groups we need to export, based on level and include_children
        $groups = [$level];
        if ($include_children) {
            $groups[] = 'include_children';
        }

        //Plain text should work for all types
        $content_type = 'text/plain';

        //Try to use better content types based on the format
        switch ($format) {
            case 'xml':
                $content_type = 'application/xml';

                break;
            case 'json':
                $content_type = 'application/json';

                break;
        }

        //Ensure that we always serialize an array. This makes it easier to import the data again.
        if (is_array($entity)) {
            $entity_array = $entity;
        } else {
            $entity_array = [$entity];
        }

        $serialized_data = $this->serializer->serialize($entity_array, $format,
                                                        [
                                                            'groups' => $groups,
                                                            'as_collection' => true,
                                                            'csv_delimiter' => ';', //Better for Excel
                                                            'xml_root_node_name' => 'PartDBExport',
                                                        ]);

        $response = new Response($serialized_data);

        $response->headers->set('Content-Type', $content_type);

        //If view option is not specified, then download the file.
        if (!$request->get('view')) {
            if ($entity instanceof AbstractNamedDBElement) {
                $entity_name = $entity->getName();
            } elseif (is_array($entity)) {
                if (empty($entity)) {
                    throw new InvalidArgumentException('$entity must not be empty!');
                }

                //Use the class name of the first element for the filename
                $reflection = new ReflectionClass($entity[0]);
                $entity_name = $reflection->getShortName();
            } else {
                throw new InvalidArgumentException('$entity type is not supported!');
            }

            $filename = 'export_'.$entity_name.'_'.$level.'.'.$format;

            // Create the disposition of the file
            $disposition = $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $filename,
                $string = preg_replace('![^'.preg_quote('-', '!').'a-z0-_9\s]+!', '', strtolower($filename))
            );
            // Set the content disposition
            $response->headers->set('Content-Disposition', $disposition);
        }

        return $response;
    }
}
