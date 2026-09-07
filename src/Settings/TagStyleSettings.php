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

namespace App\Settings;

use App\Form\Settings\TagStyleRulesCollectionType;
use Jbtronics\SettingsBundle\ParameterTypes\ArrayType;
use Jbtronics\SettingsBundle\ParameterTypes\SerializeType;
use Jbtronics\SettingsBundle\Settings\Settings;
use Jbtronics\SettingsBundle\Settings\SettingsParameter;
use Jbtronics\SettingsBundle\Settings\SettingsTrait;
use Symfony\Component\Translation\TranslatableMessage as TM;
use Symfony\Component\Validator\Constraints as Assert;

#[Settings(name: "tag_styles", label: new TM("settings.tag_styles"), description: "settings.tag_styles.help")]
#[SettingsIcon("fa-tags")]
class TagStyleSettings
{
    use SettingsTrait;

    #[SettingsParameter(
        ArrayType::class,
        label: new TM("settings.tag_styles.rules"),
        description: new TM("settings.tag_styles.rules.help"),
        options: ['type' => SerializeType::class],
        formType: TagStyleRulesCollectionType::class,
        formOptions: [
            'required' => false,
        ],
    )]
    #[Assert\Type('array')]
    #[Assert\All([new Assert\Type('array')])]
    /**
     * An ordered list of tag style rules, evaluated by TagStyleResolver.
     * @var array<int, array{match_type: string, pattern: string, color: string}>
     */
    public array $rules = [];
}
