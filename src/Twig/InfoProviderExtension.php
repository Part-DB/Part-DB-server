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


namespace App\Twig;

use App\Services\InfoProviderSystem\ProviderRegistry;
use App\Services\InfoProviderSystem\Providers\InfoProviderInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class InfoProviderExtension extends AbstractExtension
{
    public function __construct(
        private readonly ProviderRegistry $providerRegistry
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('info_provider', $this->getInfoProvider(...)),
            new TwigFunction('info_provider_label', $this->getInfoProviderName(...))
        ];
    }

    /**
     * Gets the info provider with the given key. Returns null, if the provider does not exist.
     * @param  string  $key
     * @return InfoProviderInterface|null
     */
    private function getInfoProvider(string $key): ?InfoProviderInterface
    {
        try {
            return $this->providerRegistry->getProviderByKey($key);
        }  catch (\InvalidArgumentException $exception) {
            return null;
        }
    }

    /**
     * Gets the label of the info provider with the given key. Returns null, if the provider does not exist.
     * @param  string  $key
     * @return string|null
     */
    private function getInfoProviderName(string $key): ?string
    {
        try {
            return $this->providerRegistry->getProviderByKey($key)->getProviderInfo()['name'];
        }  catch (\InvalidArgumentException $exception) {
            return null;
        }
    }
}