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


namespace App\Services\AI;

use Jbtronics\SettingsBundle\Manager\SettingsManagerInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class AIPlatformRegistry
{
    /**
     * All registered platforms, indexed by their service tag name (e.g. "openrouter", "lmstudio")
     * @var array<string, PlatformInterface> $allPlatforms
     */
    private array $allPlatforms;

    /**
     * All registered platforms, indexed by their AIPlatforms enum value (e.g. AIPlatforms::OPENROUTER->value)
     * @var array<string, PlatformInterface> $enabledPlatforms
     */
    private array $enabledPlatforms;

    public function __construct(
        SettingsManagerInterface $settingsManager,
        #[AutowireIterator(tag: 'ai.platform', indexAttribute: 'name')]
        iterable $platforms,
    ) {
        $this->allPlatforms = iterator_to_array($platforms);

        //Check which platforms are active based on the settings and store them in $activePlatforms
        $tmp = [];
        foreach (AIPlatforms::cases() as $platform) {
            if (isset($this->allPlatforms[$platform->toServiceTagName()])) {
                //Check if the platform is active by calling its isActive() on the settings class
                $settings = $settingsManager->get($platform->toSettingsClass());
                if (!$settings->isAIPlatformEnabled()) {
                    continue;
                }

                $tmp[$platform->value] = $this->allPlatforms[$platform->toServiceTagName()];
            }
        }
        $this->enabledPlatforms = $tmp;
    }

    public function getPlatform(AIPlatforms $platform): PlatformInterface
    {
        if (!isset($this->enabledPlatforms[$platform->value])) {
            throw new \InvalidArgumentException(sprintf('AI platform "%s" is not active or does not exist.', $platform->name));
        }

        return $this->enabledPlatforms[$platform->value];
    }

    /**
     * Check if the given platform is active (i.e. it is registered and its settings are properly configured)
     * @param  AIPlatforms  $platform
     * @return bool
     */
    public function isEnabled(AIPlatforms $platform): bool
    {
        return isset($this->enabledPlatforms[$platform->value]);
    }

    /**
     * Returns an array of all active platforms, indexed by their AIPlatforms enum value (e.g. AIPlatforms::OPENROUTER->value)
     * @return PlatformInterface[]
     */
    public function getEnabledPlatforms(): array
    {
        return $this->enabledPlatforms;
    }
}
