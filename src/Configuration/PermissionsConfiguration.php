<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2020 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Configuration;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class PermissionsConfiguration implements ConfigurationInterface
{
    /**
     * Generates the configuration tree builder.
     *
     * @return TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('permissions');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode->children()
            ->arrayNode('groups')
            ->arrayPrototype()
            ->children()
            ->scalarNode('label')->end();

        $rootNode->children()
            ->arrayNode('perms')
            ->arrayPrototype()
            ->children()
            ->scalarNode('label')->end()
            ->scalarNode('group')->end()
            ->arrayNode('operations')
            ->arrayPrototype()
            ->children()
            ->scalarNode('name')->end()
            ->scalarNode('label')->end()
            ->scalarNode('bit')->end()
            ->scalarNode('apiTokenRole')->defaultNull()->end()
            ->arrayNode('alsoSet')
            ->beforeNormalization()->castToArray()->end()->scalarPrototype()->end();

        return $treeBuilder;
    }
}
