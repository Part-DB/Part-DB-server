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


namespace App\Settings\BehaviorSettings;

use App\Settings\SettingsIcon;
use Jbtronics\SettingsBundle\ParameterTypes\ArrayType;
use Jbtronics\SettingsBundle\ParameterTypes\EnumType;
use Jbtronics\SettingsBundle\Settings\Settings;
use Jbtronics\SettingsBundle\Settings\SettingsParameter;
use Jbtronics\SettingsBundle\Settings\SettingsTrait;
use Symfony\Component\Translation\TranslatableMessage as TM;
use Symfony\Component\Validator\Constraints as Assert;

#[Settings(label: new TM("settings.behavior.sidebar"))]
#[SettingsIcon('fa-border-top-left')]
class SidebarSettings
{
    use SettingsTrait;


    /**
     * @var SidebarItems[] The items to show in the sidebar.
     */
    #[SettingsParameter(ArrayType::class,
        label: new TM("settings.behavior.sidebar.items"),
        description: new TM("settings.behavior.sidebar.items.help"),
        options: ['type' => EnumType::class, 'options' => ['class' => SidebarItems::class]],
        formType: \Symfony\Component\Form\Extension\Core\Type\EnumType::class,
        formOptions: ['class' => SidebarItems::class, 'multiple' => true, 'ordered' => true]
    )]
    #[Assert\NotBlank()]
    public array $items = [SidebarItems::CATEGORIES, SidebarItems::PROJECTS, SidebarItems::TOOLS];

    /**
     * @var bool Whether categories, etc. should be grouped under a root node or put directly into the sidebar trees.
     */
    #[SettingsParameter(
        label: new TM("settings.behavior.sidebar.rootNodeEnabled"),
        description: new TM("settings.behavior.sidebar.rootNodeEnabled.help")
    )]
    public bool $rootNodeEnabled = true;

    /**
     * @var bool Whether the root node should be expanded by default, or not.
     */
    #[SettingsParameter(label: new TM("settings.behavior.sidebar.rootNodeExpanded"))]
    public bool $rootNodeExpanded = true;
}