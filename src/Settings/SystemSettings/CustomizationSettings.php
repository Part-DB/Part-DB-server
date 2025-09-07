<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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

use App\Form\Type\RichTextEditorType;
use App\Form\Type\ThemeChoiceType;
use App\Settings\SettingsIcon;
use App\Validator\Constraints\ValidTheme;
use Jbtronics\SettingsBundle\Metadata\EnvVarMode;
use Jbtronics\SettingsBundle\ParameterTypes\ArrayType;
use Jbtronics\SettingsBundle\ParameterTypes\EnumType;
use Jbtronics\SettingsBundle\Settings\Settings;
use Jbtronics\SettingsBundle\Settings\SettingsParameter;
use Jbtronics\SettingsBundle\Settings\SettingsTrait;
use Symfony\Component\Translation\TranslatableMessage as TM;
use Symfony\Component\Validator\Constraints as Assert;

#[Settings(name: "customization", label: new TM("settings.system.customization"))]
#[SettingsIcon("fa-paint-roller")]
class CustomizationSettings
{
    use SettingsTrait;

    #[SettingsParameter(
        label: new TM("settings.system.customization.instanceName"),
        description: new TM("settings.system.customization.instanceName.help"),
        envVar: "INSTANCE_NAME", envVarMode: EnvVarMode::OVERWRITE,
    )]
    public string $instanceName = "Part-DB";

    #[SettingsParameter(
        label: new TM("settings.system.customization.theme"),
        formType: ThemeChoiceType::class, formOptions: ['placeholder' => false]
    )]
    #[ValidTheme]
    public string $theme = 'bootstrap';

    #[SettingsParameter(
        label: new TM("settings.system.customization.banner"),
        formType: RichTextEditorType::class, formOptions: ['mode' => 'markdown-full'],
        envVar: "BANNER", envVarMode: EnvVarMode::OVERWRITE,
    )]
    public ?string $banner = null;

    /**
     * @var HomepageItems[] The items to show in the sidebar.
     */
    #[SettingsParameter(ArrayType::class,
        label: new TM("settings.behavior.hompepage.items"),
        description: new TM("settings.behavior.homepage.items.help"),
        options: ['type' => EnumType::class, 'options' => ['class' => HomepageItems::class]],
        formType: \Symfony\Component\Form\Extension\Core\Type\EnumType::class,
        formOptions: ['class' => HomepageItems::class, 'multiple' => true, 'ordered' => true]
    )]
    #[Assert\NotBlank()]
    #[Assert\Unique()]
    public array $homepageitems = [HomepageItems::SEARCH, HomepageItems::BANNER, HomepageItems::FIRST_STEPS, HomepageItems::LICENSE, HomepageItems::LAST_ACTIVITY];
}
