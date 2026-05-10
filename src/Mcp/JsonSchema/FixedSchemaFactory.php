<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Mcp\JsonSchema;

use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Metadata\Operation;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * Overwrite the default JSON Schema factory to resolve $ref and allOf into a flat schema.
 * This is a workaround until https://github.com/api-platform/core/pull/7962 is merged
 */
#[AsAlias('api_platform.mcp.json_schema.schema_factory')]
readonly class FixedSchemaFactory implements SchemaFactoryInterface
{
    public function __construct(
        private readonly SchemaFactoryInterface $decorated,
    ) {
    }

    public function buildSchema(string $className, string $format = 'json', string $type = Schema::TYPE_OUTPUT, ?Operation $operation = null, ?Schema $schema = null, ?array $serializerContext = null, bool $forceCollection = false): Schema
    {
        $schema = $this->decorated->buildSchema($className, $format, $type, $operation, $schema, $serializerContext, $forceCollection);

        $definitions = [];
        foreach ($schema->getDefinitions() as $key => $definition) {
            $definitions[$key] = $definition instanceof \ArrayObject ? $definition->getArrayCopy() : (array) $definition;
        }

        $rootKey = $schema->getRootDefinitionKey();
        if (null !== $rootKey) {
            $root = $definitions[$rootKey] ?? [];
        } else {
            // Collection schemas (and others) put allOf/type directly on the root
            $root = $schema->getArrayCopy(false);
        }

        $flat = self::resolveNode($root, $definitions);

        $flatSchema = new Schema(Schema::VERSION_JSON_SCHEMA);
        unset($flatSchema['$schema']);
        foreach ($flat as $key => $value) {
            $flatSchema[$key] = $value;
        }

        return $flatSchema;
    }

    /**
     * Recursively resolve $ref, allOf, and nested structures into a flat schema node.
     *
     * @param array $resolving Tracks the current $ref resolution chain to detect circular references
     */
    public static function resolveNode(array|\ArrayObject $node, array $definitions, array &$resolving = []): array
    {
        if ($node instanceof \ArrayObject) {
            $node = $node->getArrayCopy();
        }

        if (isset($node['$ref'])) {
            $refKey = str_replace('#/definitions/', '', $node['$ref']);
            if (!isset($definitions[$refKey]) || isset($resolving[$refKey])) {
                return ['type' => 'object'];
            }
            $resolving[$refKey] = true;
            $resolved = self::resolveNode($definitions[$refKey], $definitions, $resolving);
            unset($resolving[$refKey]);

            return $resolved;
        }

        if (isset($node['allOf'])) {
            $merged = ['type' => 'object', 'properties' => []];
            $requiredSets = [];
            foreach ($node['allOf'] as $entry) {
                $resolved = self::resolveNode($entry, $definitions, $resolving);
                if (isset($resolved['properties'])) {
                    foreach ($resolved['properties'] as $k => $v) {
                        $merged['properties'][$k] = $v;
                    }
                }
                if (isset($resolved['required'])) {
                    $requiredSets[] = $resolved['required'];
                }
            }

            if ($requiredSets) {
                $merged['required'] = array_merge(...$requiredSets);
            }
            if ([] === $merged['properties']) {
                unset($merged['properties']);
            }
            if (isset($node['description'])) {
                $merged['description'] = $node['description'];
            }

            return self::resolveDeep($merged, $definitions, $resolving);
        }

        // oneOf/anyOf nodes must not receive a type fallback — their type is expressed
        // through the sub-schemas. Adding 'type: object' here would break schemas like
        // HydraItemBaseSchema's @context, which is oneOf: [string, object].
        if (isset($node['oneOf']) || isset($node['anyOf'])) {
            return self::resolveDeep($node, $definitions, $resolving);
        }

        if (!isset($node['type'])) {
            $node['type'] = 'object';
        }

        return self::resolveDeep($node, $definitions, $resolving);
    }

    /**
     * Recursively resolve nested properties and array items.
     */
    private static function resolveDeep(array $node, array $definitions, array &$resolving): array
    {
        if (isset($node['items'])) {
            $node['items'] = self::resolveNode(
                $node['items'] instanceof \ArrayObject ? $node['items']->getArrayCopy() : $node['items'],
                $definitions,
                $resolving,
            );
        }

        if (isset($node['properties']) && \is_array($node['properties'])) {
            foreach ($node['properties'] as $propName => $propSchema) {
                $node['properties'][$propName] = self::resolveNode(
                    $propSchema instanceof \ArrayObject ? $propSchema->getArrayCopy() : $propSchema,
                    $definitions,
                    $resolving,
                );
            }
        }

        return $node;
    }
}
