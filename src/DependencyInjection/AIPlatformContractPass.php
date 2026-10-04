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

namespace App\DependencyInjection;

use App\Services\AI\Contract\AIPlatformContractFactory;
use Symfony\AI\Platform\Contract;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Injects the contracts of the AIPlatformContractFactory into the AI platforms defined by the AI bundle, so that they
 * can send files (images and PDF documents) to the models. The bundle offers no configuration for this: it passes null
 * as contract to most platforms, which then use the default contract of the bridge.
 */
#[Exclude]
final class AIPlatformContractPass implements CompilerPassInterface
{
    private const OPENAI_COMPATIBLE_CONTRACT = 'app.ai.contract.openai_compatible';

    /** @var array<string, int> The position of the contract in the arguments of the platform factories */
    private const CONTRACT_ARGUMENT_INDEX = [
        'ai.platform.openrouter' => 3,
        'ai.platform.lmstudio' => 3,
        'ai.platform.generic.' => 4, //Prefix, there is one service per configured generic platform
    ];

    public function process(ContainerBuilder $container): void
    {
        $container->register(self::OPENAI_COMPATIBLE_CONTRACT, Contract::class)
            ->setFactory([AIPlatformContractFactory::class, 'createOpenAICompatible']);

        foreach (array_keys($container->findTaggedServiceIds('ai.platform')) as $id) {
            foreach (self::CONTRACT_ARGUMENT_INDEX as $prefix => $index) {
                if (!str_starts_with($id, $prefix)) {
                    continue;
                }

                $definition = $container->getDefinition($id);
                //Do not replace a contract, if a future version of the bundle passes one
                if ($definition->getArgument($index) === null) {
                    $definition->replaceArgument($index, new Reference(self::OPENAI_COMPATIBLE_CONTRACT));
                }
            }
        }

        if ($container->hasDefinition('ai.platform.contract.ollama')) {
            $container->getDefinition('ai.platform.contract.ollama')
                ->setFactory([AIPlatformContractFactory::class, 'createOllama'])
                ->setArguments([]);
        }
    }
}
