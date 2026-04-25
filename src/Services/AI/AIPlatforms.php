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

use App\Settings\AISettings\LMStudioSettings;
use App\Settings\AISettings\OpenRouterSettings;

enum AIPlatforms: string
{
    case OPENROUTER = 'openrouter';
    case LMSTUDIO = 'lmstudio';

    /**
     * Returns the name attribute of the service tag for this platform, which is used to register the platform in the AIPlatformRegistry
     * @return string
     */
    public function toServiceTagName(): string
    {
        return $this->value;
    }

    /**
     * Returns the class name of the settings class for this platform, which implements AIPlatformSettingsInterface
     * @return string
     * @phpstan-return class-string<AIPlatformSettingsInterface>
     */
    public function toSettingsClass(): string
    {
        return match ($this) {
            self::LMSTUDIO => LMStudioSettings::class,
            self::OPENROUTER => OpenRouterSettings::class,

            default => throw new \InvalidArgumentException(sprintf('No settings class defined for AI platform "%s".', $this->name)),
        };
    }
}
