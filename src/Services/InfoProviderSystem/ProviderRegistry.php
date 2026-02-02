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


namespace App\Services\InfoProviderSystem;

use App\Services\InfoProviderSystem\Providers\InfoProviderInterface;
use App\Services\InfoProviderSystem\Providers\URLHandlerInfoProviderInterface;

/**
 * This class keeps track of all registered info providers and allows to find them by their key
 * @see \App\Tests\Services\InfoProviderSystem\ProviderRegistryTest
 */
final class ProviderRegistry
{
    /**
     * @var InfoProviderInterface[] The info providers index by their keys
     * @phpstan-var array<string, InfoProviderInterface>
     */
    private array $providers_by_name = [];

    /**
     * @var InfoProviderInterface[] The enabled providers indexed by their keys
     */
    private array $providers_active = [];

    /**
     * @var InfoProviderInterface[] The disabled providers indexed by their keys
     */
    private array $providers_disabled = [];

    private array $providers_by_domain = [];

    /**
     * @var bool Whether the registry has been initialized
     */
    private bool $initialized = false;

    /**
     * @param  iterable<InfoProviderInterface>  $providers
     */
    public function __construct(private readonly iterable $providers)
    {
        //We do not initialize the structures here, because we do not want to do unnecessary work
        //We do this lazy on the first call to getProviders()
    }

    /**
     * Initializes the registry, we do this lazy to avoid unnecessary work, on construction, which is always called
     * even if the registry is not used
     * @return void
     */
    private function initStructures(): void
    {
        foreach ($this->providers as $provider) {
            $key = $provider->getProviderKey();

            if (isset($this->providers_by_name[$key])) {
                throw new \LogicException("Provider with key $key already registered");
            }

            $this->providers_by_name[$key] = $provider;
            if ($provider->isActive()) {
                $this->providers_active[$key] = $provider;
                if ($provider instanceof URLHandlerInfoProviderInterface) {
                    foreach ($provider->getHandledDomains() as $domain) {
                        if (isset($this->providers_by_domain[$domain])) {
                            throw new \LogicException("Domain $domain is already handled by another provider");
                        }
                        $this->providers_by_domain[$domain] = $provider;
                    }
                }
            } else {
                $this->providers_disabled[$key] = $provider;
            }
        }

        $this->initialized = true;
    }

    /**
     * Returns an array of all registered providers (enabled and disabled)
     * @return InfoProviderInterface[]
     */
    public function getProviders(): array
    {
        if (!$this->initialized) {
            $this->initStructures();
        }

        return $this->providers_by_name;
    }

    /**
     * Returns the provider identified by the given key
     * @param  string  $key
     * @return InfoProviderInterface
     * @throws \InvalidArgumentException If the provider with the given key does not exist
     */
    public function getProviderByKey(string $key): InfoProviderInterface
    {
        if (!$this->initialized) {
            $this->initStructures();
        }

        return $this->providers_by_name[$key] ?? throw new \InvalidArgumentException("Provider with key $key not found");
    }

    /**
     * Returns an array of all active providers
     * @return InfoProviderInterface[]
     */
    public function getActiveProviders(): array
    {
        if (!$this->initialized) {
            $this->initStructures();
        }

        return $this->providers_active;
    }

    /**
     * Returns an array of all disabled providers
     * @return InfoProviderInterface[]
     */
    public function getDisabledProviders(): array
    {
        if (!$this->initialized) {
            $this->initStructures();
        }

        return $this->providers_disabled;
    }

    public function getProviderHandlingDomain(string $domain): (InfoProviderInterface&URLHandlerInfoProviderInterface)|null
    {
        if (!$this->initialized) {
            $this->initStructures();
        }

        //Check if the domain is directly existing:
        if (isset($this->providers_by_domain[$domain])) {
            return $this->providers_by_domain[$domain];
        }

        //Otherwise check for subdomains:
        $parts = explode('.', $domain);
        while (count($parts) > 2) {
            array_shift($parts);
            $check_domain = implode('.', $parts);
            if (isset($this->providers_by_domain[$check_domain])) {
                return $this->providers_by_domain[$check_domain];
            }
        }

        //If we found nothing, return null
        return null;
    }
}
