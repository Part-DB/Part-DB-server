<?php
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

declare(strict_types=1);


namespace App\ApiPlatform;

use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Operation;
use App\Entity\Attachments\Attachment;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\PropertyInfo\Type;

/**
 * This decorator adds the properties given by DocumentedAPIProperty attributes on the classes to the schema.
 */
#[AsDecorator('api_platform.json_schema.schema_factory')]
class AddDocumentedAPIPropertiesJSONSchemaFactory implements SchemaFactoryInterface
{

    public function __construct(private readonly SchemaFactoryInterface $decorated)
    {
    }

    public function buildSchema(
        string $className,
        string $format = 'json',
        string $type = Schema::TYPE_OUTPUT,
        Operation $operation = null,
        Schema $schema = null,
        array $serializerContext = null,
        bool $forceCollection = false
    ): Schema {


        $schema = $this->decorated->buildSchema($className, $format, $type, $operation, $schema, $serializerContext, $forceCollection);

        //Check if there is are DocumentedAPIProperty attributes on the class
        $reflectionClass = new \ReflectionClass($className);
        $attributes = $reflectionClass->getAttributes(DocumentedAPIProperty::class);
        foreach ($attributes as $attribute) {
            /** @var DocumentedAPIProperty $api_property */
            $api_property = $attribute->newInstance();
            $this->addPropertyToSchema($schema, $api_property->schemaName, $api_property->property,
                $api_property, $serializerContext ?? [], $format);
        }

        /*if ($className === Attachment::class) {
            $api_property = new ApiProperty(description: 'Test');
            $this->buildPropertySchema($schema, 'Attachment-Read', 'media_url', $api_property, $serializerContext ?? [],
                $format);
        }*/

        //Add media_url and thumbnail_url to the Attachment schema
        /*if ($className === Attachment::class) {
            $tmp = $schema->getDefinitions()->getArrayCopy();
            $tmp['properties']['media_url'] = [
                'type' => 'string',
                'readOnly' => true,
                'format' => 'uri',
                'description' => 'The URL to the attachment',
            ];
            $tmp['properties']['thumbnail_url'] = [
                'type' => 'string',
                'readOnly' => true,
                'format' => 'uri',
                'description' => 'The URL to the thumbnail of the attachment',
            ];
            $schema->setDefinitions(new \ArrayObject($tmp));
        }*/

        //Fd
        return $schema;
    }

    private function addPropertyToSchema(Schema $schema, string $definitionName, string $normalizedPropertyName, DocumentedAPIProperty $propertyMetadata, array $serializerContext, string $format): void
    {
        $version = $schema->getVersion();
        $swagger = Schema::VERSION_SWAGGER === $version;

        $propertySchema = [];

        if (false === $propertyMetadata->writeable) {
            $propertySchema['readOnly'] = true;
        }
        if (!$swagger && false === $propertyMetadata->readable) {
            $propertySchema['writeOnly'] = true;
        }
        if (null !== $description = $propertyMetadata->description) {
            $propertySchema['description'] = $description;
        }

        $deprecationReason = $propertyMetadata->deprecationReason;

        // see https://github.com/json-schema-org/json-schema-spec/pull/737
        if (!$swagger && null !== $deprecationReason) {
            $propertySchema['deprecated'] = true;
        }

        if (!empty($default = $propertyMetadata->default)) {
            if ($default instanceof \BackedEnum) {
                $default = $default->value;
            }
            $propertySchema['default'] = $default;
        }

        if (!empty($example = $propertyMetadata->example)) {
            $propertySchema['example'] = $example;
        }

        if (!isset($propertySchema['example']) && isset($propertySchema['default'])) {
            $propertySchema['example'] = $propertySchema['default'];
        }

        $propertySchema['type'] = $propertyMetadata->type;
        $propertySchema['nullable'] = $propertyMetadata->nullable;

        $propertySchema = new \ArrayObject($propertySchema);

        $schema->getDefinitions()[$definitionName]['properties'][$normalizedPropertyName] = $propertySchema;
    }


}