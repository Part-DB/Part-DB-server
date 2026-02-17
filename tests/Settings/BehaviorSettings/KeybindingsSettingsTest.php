<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2025 Jan Böhmer (https://github.com/jbtronics)
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
namespace App\Tests\Settings\BehaviorSettings;

use App\Settings\BehaviorSettings\KeybindingsSettings;
use App\Tests\SettingsTestHelper;
use PHPUnit\Framework\TestCase;

final class KeybindingsSettingsTest extends TestCase
{
    /**
     * Test that the default value for enableSpecialCharacters is true
     */
    public function testDefaultValueIsTrue(): void
    {
        $settings = SettingsTestHelper::createSettingsDummy(KeybindingsSettings::class);
        
        $this->assertTrue($settings->enableSpecialCharacters);
    }

    /**
     * Test that enableSpecialCharacters can be set to false
     */
    public function testCanBeDisabled(): void
    {
        $settings = SettingsTestHelper::createSettingsDummy(KeybindingsSettings::class);
        $settings->enableSpecialCharacters = false;
        
        $this->assertFalse($settings->enableSpecialCharacters);
    }

    /**
     * Test that enableSpecialCharacters can be set to true
     */
    public function testCanBeEnabled(): void
    {
        $settings = SettingsTestHelper::createSettingsDummy(KeybindingsSettings::class);
        $settings->enableSpecialCharacters = false;
        $settings->enableSpecialCharacters = true;
        
        $this->assertTrue($settings->enableSpecialCharacters);
    }

    /**
     * Test that the settings class has the correct type for enableSpecialCharacters
     */
    public function testPropertyTypeIsBool(): void
    {
        $settings = SettingsTestHelper::createSettingsDummy(KeybindingsSettings::class);
        
        $reflection = new \ReflectionClass($settings);
        $property = $reflection->getProperty('enableSpecialCharacters');
        
        $this->assertTrue($property->hasType());
        $this->assertEquals('bool', $property->getType()->getName());
    }
}
