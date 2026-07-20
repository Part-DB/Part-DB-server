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

namespace App\State\Mcp;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Exceptions\InfoProviderNotActiveException;
use App\Mcp\DTO\InfoProviderSearchInput;
use App\Services\InfoProviderSystem\PartInfoRetriever;
use App\Services\InfoProviderSystem\ProviderRegistry;
use App\Settings\InfoProviderSystem\InfoProviderGeneralSettings;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class SearchInfoProvidersProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly PartInfoRetriever $infoRetriever,
        private readonly ProviderRegistry $providerRegistry,
        private readonly InfoProviderGeneralSettings $infoProviderSettings,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        if (!$data instanceof InfoProviderSearchInput) {
            throw new BadRequestHttpException('Expected InfoProviderSearchInput');
        }

        $providers = $this->resolveProviderKeys($data->providers);

        try {
            return $this->infoRetriever->searchByKeyword($data->keyword, $providers);
        } catch (InfoProviderNotActiveException|\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }
    }

    /**
     * Resolves the provider keys to search in. If none are given, falls back to the active default search
     * providers configured in the system settings (mirroring the behavior of the info providers search page).
     * @param  string[]|null  $providerKeys
     * @return string[]
     */
    private function resolveProviderKeys(?array $providerKeys): array
    {
        if (!empty($providerKeys)) {
            return $providerKeys;
        }

        $providers = [];
        foreach ($this->infoProviderSettings->defaultSearchProviders as $providerKey) {
            try {
                if ($this->providerRegistry->getProviderByKey($providerKey)->isActive()) {
                    $providers[] = $providerKey;
                }
            } catch (\InvalidArgumentException) {
                //Ignore providers configured as default, which do not exist (anymore)
            }
        }

        if ($providers === []) {
            throw new BadRequestHttpException(sprintf(
                'No providers were given and no default search providers are configured or active. Available provider keys: %s',
                implode(', ', array_keys($this->providerRegistry->getActiveProviders()))
            ));
        }

        return $providers;
    }
}
