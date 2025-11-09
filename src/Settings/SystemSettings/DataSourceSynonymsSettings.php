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

declare(strict_types=1);

namespace App\Settings\SystemSettings;

use App\Form\Type\DataSourceSynonymsCollectionType;
use App\Services\ElementTypes;
use App\Settings\SettingsIcon;
use Jbtronics\SettingsBundle\ParameterTypes\ArrayType;
use Jbtronics\SettingsBundle\ParameterTypes\SerializeType;
use Jbtronics\SettingsBundle\ParameterTypes\StringType;
use Jbtronics\SettingsBundle\Settings\Settings;
use Jbtronics\SettingsBundle\Settings\SettingsParameter;
use Jbtronics\SettingsBundle\Settings\SettingsTrait;
use Symfony\Component\Translation\TranslatableMessage as TM;
use Symfony\Component\Validator\Constraints as Assert;

#[Settings(label: new TM("settings.system.data_source_synonyms"))]
#[SettingsIcon("fa-language")]
class DataSourceSynonymsSettings
{
    use SettingsTrait;

    #[SettingsParameter(
        ArrayType::class,
        label: new TM("settings.system.data_source_synonyms.configuration"),
        description: new TM("settings.system.data_source_synonyms.configuration.help"),
        options: ['type' => SerializeType::class],
        formType: DataSourceSynonymsCollectionType::class,
        formOptions: [
            'required' => false,
        ],
    )]
    #[Assert\Type('array')]
    #[Assert\All([new Assert\Type('array')])]
    /**
     * @var array<string, array<string, array{singular: string, plural: string}>> $customTypeLabels
     * An array of the form: [
     * 'category' => [
     *    'en' => ['singular' => 'Category', 'plural' => 'Categories'],
     *    'de' => ['singular' => 'Kategorie', 'plural' => 'Kategorien'],
     * ],
     * 'manufacturer' => [
     *   'en' => ['singular' => 'Manufacturer', 'plural' =>'Manufacturers'],
     *   ],
     * ]
     */
    public array $customTypeLabels = [];

    public function isCustomLabelDefinedForType(ElementTypes $type): bool
    {
        return isset($this->customTypeLabels[$type->value]);
    }
}
