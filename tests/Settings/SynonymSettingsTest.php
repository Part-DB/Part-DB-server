<?php
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

namespace App\Tests\Settings;

use App\Services\ElementTypes;
use App\Settings\SynonymSettings;
use App\Tests\SettingsTestHelper;
use PHPUnit\Framework\TestCase;

class SynonymSettingsTest extends TestCase
{

    public function testGetSingularSynonymForType(): void
    {
        $settings = SettingsTestHelper::createSettingsDummy(SynonymSettings::class);
        $settings->typeSynonyms['category'] = [
            'en' => ['singular' => 'Category', 'plural' => 'Categories'],
            'de' => ['singular' => 'Kategorie', 'plural' => 'Kategorien'],
        ];

        $this->assertEquals('Category', $settings->getSingularSynonymForType(ElementTypes::CATEGORY, 'en'));
        $this->assertEquals('Kategorie', $settings->getSingularSynonymForType(ElementTypes::CATEGORY, 'de'));

        //If no synonym is defined, it should return null
        $this->assertNull($settings->getSingularSynonymForType(ElementTypes::MANUFACTURER, 'en'));
    }

    public function testIsSynonymDefinedForType(): void
    {
        $settings = SettingsTestHelper::createSettingsDummy(SynonymSettings::class);
        $settings->typeSynonyms['category'] = [
            'en' => ['singular' => 'Category', 'plural' => 'Categories'],
            'de' => ['singular' => 'Kategorie', 'plural' => 'Kategorien'],
        ];

        $settings->typeSynonyms['supplier'] = [];

        $this->assertTrue($settings->isSynonymDefinedForType(ElementTypes::CATEGORY));
        $this->assertFalse($settings->isSynonymDefinedForType(ElementTypes::FOOTPRINT));
        $this->assertFalse($settings->isSynonymDefinedForType(ElementTypes::SUPPLIER));
    }

    public function testGetPluralSynonymForType(): void
    {
        $settings = SettingsTestHelper::createSettingsDummy(SynonymSettings::class);
        $settings->typeSynonyms['category'] = [
            'en' => ['singular' => 'Category', 'plural' => 'Categories'],
            'de' => ['singular' => 'Kategorie',],
        ];

        $this->assertEquals('Categories', $settings->getPluralSynonymForType(ElementTypes::CATEGORY, 'en'));
        //Fallback to singular if no plural is defined
        $this->assertEquals('Kategorie', $settings->getPluralSynonymForType(ElementTypes::CATEGORY, 'de'));

        //If no synonym is defined, it should return null
        $this->assertNull($settings->getPluralSynonymForType(ElementTypes::MANUFACTURER, 'en'));
    }
}
