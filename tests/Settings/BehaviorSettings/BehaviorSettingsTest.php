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

use App\Settings\BehaviorSettings\BehaviorSettings;
use App\Settings\BehaviorSettings\KeybindingsSettings;
use App\Tests\SettingsTestHelper;
use PHPUnit\Framework\TestCase;

final class BehaviorSettingsTest extends TestCase
{
    /**
     * Test that BehaviorSettings has the keybindings property
     */
    public function testHasKeybindingsProperty(): void
    {
        $settings = SettingsTestHelper::createSettingsDummy(BehaviorSettings::class);
        
        $reflection = new \ReflectionClass($settings);
        $this->assertTrue($reflection->hasProperty('keybindings'));
    }

    /**
     * Test that keybindings property is nullable and of correct type
     */
    public function testKeybindingsPropertyType(): void
    {
        $settings = SettingsTestHelper::createSettingsDummy(BehaviorSettings::class);
        
        $reflection = new \ReflectionClass($settings);
        $property = $reflection->getProperty('keybindings');
        
        $this->assertTrue($property->hasType());
        
        $type = $property->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $type);
        $this->assertEquals(KeybindingsSettings::class, $type->getName());
        $this->assertTrue($type->allowsNull());
    }

    /**
     * Test that keybindings property defaults to null
     */
    public function testKeybindingsDefaultsToNull(): void
    {
        $settings = SettingsTestHelper::createSettingsDummy(BehaviorSettings::class);
        
        $this->assertNull($settings->keybindings);
    }
}
