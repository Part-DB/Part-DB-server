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

/**
 * Tests for App\Services\AI\AIPlatforms
 */
declare(strict_types=1);

namespace App\Tests\Services\AI;

use App\Services\AI\AIPlatforms;
use App\Settings\AISettings\GenericAISettings;
use App\Settings\AISettings\LMStudioSettings;
use App\Settings\AISettings\OllamaSettings;
use App\Settings\AISettings\OpenRouterSettings;
use PHPUnit\Framework\TestCase;

class AIPlatformsTest extends TestCase
{
    public function testToServiceTagName(): void
    {
        $this->assertSame('openrouter', AIPlatforms::OPENROUTER->toServiceTagName());
        $this->assertSame('lmstudio', AIPlatforms::LMSTUDIO->toServiceTagName());
        $this->assertSame('ollama', AIPlatforms::OLLAMA->toServiceTagName());

        //The generic platform is backed by the symfony/ai-generic-platform bridge, registered under the
        //"default" name, which tags its service as "generic.default" instead of just "generic"
        $this->assertSame('generic.default', AIPlatforms::GENERIC->toServiceTagName());
    }

    public function testToSettingsClass(): void
    {
        $this->assertSame(OpenRouterSettings::class, AIPlatforms::OPENROUTER->toSettingsClass());
        $this->assertSame(LMStudioSettings::class, AIPlatforms::LMSTUDIO->toSettingsClass());
        $this->assertSame(OllamaSettings::class, AIPlatforms::OLLAMA->toSettingsClass());
        $this->assertSame(GenericAISettings::class, AIPlatforms::GENERIC->toSettingsClass());
    }
}
