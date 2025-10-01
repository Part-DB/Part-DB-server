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
use Jbtronics\SettingsBundle\Metadata\EnvVarMode;
use Jbtronics\SettingsBundle\ParameterTypes\ArrayType;
use Jbtronics\SettingsBundle\ParameterTypes\EnumType;
use Jbtronics\SettingsBundle\Settings\Settings;
use Jbtronics\SettingsBundle\Settings\SettingsParameter;
use Jbtronics\SettingsBundle\Settings\SettingsTrait;
use Symfony\Component\Translation\TranslatableMessage as TM;
use Symfony\Component\Validator\Constraints as Assert;

#[Settings(label: new TM("settings.behavior.table"))]
#[SettingsIcon('fa-table')]
class TableSettings
{
    use SettingsTrait;

    #[SettingsParameter(
        label: new TM("settings.behavior.table.default_page_size"),
        description: new TM("settings.behavior.table.default_page_size.help"),
        envVar: "int:TABLE_DEFAULT_PAGE_SIZE",
        envVarMode: EnvVarMode::OVERWRITE,
    )]
    #[Assert\AtLeastOneOf(constraints:
        [
            new Assert\Positive(),
            new Assert\EqualTo(value: -1)
        ]
    )]
    public int $fullDefaultPageSize = 50;

    /** @var PartTableColumns[] */
    #[SettingsParameter(ArrayType::class,
        label: new TM("settings.behavior.table.parts_default_columns"),
        description: new TM("settings.behavior.table.parts_default_columns.help"),
        options: ['type' => EnumType::class, 'options' => ['class' => PartTableColumns::class]],
        formType: \Symfony\Component\Form\Extension\Core\Type\EnumType::class,
        formOptions: ['class' => PartTableColumns::class, 'multiple' => true, 'ordered' => true],
        envVar: "TABLE_PARTS_DEFAULT_COLUMNS", envVarMode: EnvVarMode::OVERWRITE, envVarMapper: [self::class, 'mapPartsDefaultColumnsEnv']
    )]
    #[Assert\NotBlank()]
    #[Assert\Unique()]
    #[Assert\All([new Assert\Type(PartTableColumns::class)])]
    public array $partsDefaultColumns = [PartTableColumns::NAME, PartTableColumns::DESCRIPTION,
        PartTableColumns::CATEGORY, PartTableColumns::FOOTPRINT, PartTableColumns::MANUFACTURER,
        PartTableColumns::LOCATION, PartTableColumns::AMOUNT];

    /** @var AssemblyTableColumns[] */
    #[SettingsParameter(ArrayType::class,
        label: new TM("settings.behavior.table.assemblies_default_columns"),
        description: new TM("settings.behavior.table.assemblies_default_columns.help"),
        options: ['type' => EnumType::class, 'options' => ['class' => AssemblyTableColumns::class]],
        formType: \Symfony\Component\Form\Extension\Core\Type\EnumType::class,
        formOptions: ['class' => AssemblyTableColumns::class, 'multiple' => true, 'ordered' => true],
        envVar: "TABLE_ASSEMBLIES_DEFAULT_COLUMNS", envVarMode: EnvVarMode::OVERWRITE, envVarMapper: [self::class, 'mapAssembliesDefaultColumnsEnv']
    )]
    #[Assert\NotBlank()]
    #[Assert\Unique()]
    #[Assert\All([new Assert\Type(AssemblyTableColumns::class)])]
    public array $assembliesDefaultColumns = [AssemblyTableColumns::ID, AssemblyTableColumns::IPN, AssemblyTableColumns::NAME,
        AssemblyTableColumns::DESCRIPTION, AssemblyTableColumns::REFERENCED_ASSEMBLIES, AssemblyTableColumns::EDIT];

    /** @var AssemblyBomTableColumns[] */
    #[SettingsParameter(ArrayType::class,
        label: new TM("settings.behavior.table.assemblies_bom_default_columns"),
        description: new TM("settings.behavior.table.assemblies_bom_default_columns.help"),
        options: ['type' => EnumType::class, 'options' => ['class' => AssemblyBomTableColumns::class]],
        formType: \Symfony\Component\Form\Extension\Core\Type\EnumType::class,
        formOptions: ['class' => AssemblyBomTableColumns::class, 'multiple' => true, 'ordered' => true],
        envVar: "TABLE_ASSEMBLIES_BOM_DEFAULT_COLUMNS", envVarMode: EnvVarMode::OVERWRITE, envVarMapper: [self::class, 'mapAssemblyBomsDefaultColumnsEnv']
    )]
    #[Assert\NotBlank()]
    #[Assert\Unique()]
    #[Assert\All([new Assert\Type(AssemblyBomTableColumns::class)])]

    public array $assembliesBomDefaultColumns = [AssemblyBomTableColumns::QUANTITY, AssemblyBomTableColumns::ID,
        AssemblyBomTableColumns::IPN, AssemblyBomTableColumns::NAME, AssemblyBomTableColumns::DESCRIPTION];

    #[SettingsParameter(label: new TM("settings.behavior.table.preview_image_min_width"),
        formOptions: ['attr' => ['min' => 1, 'max' => 100]],
        envVar: "int:TABLE_IMAGE_PREVIEW_MIN_SIZE", envVarMode: EnvVarMode::OVERWRITE
    )]
    #[Assert\Range(min: 1, max: 100)]
    public int $previewImageMinWidth = 20;

    #[SettingsParameter(label: new TM("settings.behavior.table.preview_image_max_width"),
        formOptions: ['attr' => ['min' => 1, 'max' => 100]],
        envVar: "int:TABLE_IMAGE_PREVIEW_MAX_SIZE", envVarMode: EnvVarMode::OVERWRITE
    )]
    #[Assert\Range(min: 1, max: 100)]
    #[Assert\GreaterThanOrEqual(propertyPath: 'previewImageMinWidth')]
    public int $previewImageMaxWidth = 35;

    public static function mapPartsDefaultColumnsEnv(string $columns): array
    {
        $exploded = explode(',', $columns);
        $ret = [];
        foreach ($exploded as $column) {
            $enum = PartTableColumns::tryFrom($column);
            if (!$enum) {
                throw new \InvalidArgumentException("Invalid column '$column' in TABLE_PARTS_DEFAULT_COLUMNS");
            }

            $ret[] = $enum;
        }

        return $ret;
    }

    public static function mapAssembliesDefaultColumnsEnv(string $columns): array
    {
        $exploded = explode(',', $columns);
        $ret = [];
        foreach ($exploded as $column) {
            $enum = AssemblyTableColumns::tryFrom($column);
            if (!$enum) {
                throw new \InvalidArgumentException("Invalid column '$column' in TABLE_ASSEMBLIES_DEFAULT_COLUMNS");
            }

            $ret[] = $enum;
        }

        return $ret;
    }

    public static function mapAssemblyBomsDefaultColumnsEnv(string $columns): array
    {
        $exploded = explode(',', $columns);
        $ret = [];
        foreach ($exploded as $column) {
            $enum = AssemblyBomTableColumns::tryFrom($column);
            if (!$enum) {
                throw new \InvalidArgumentException("Invalid column '$column' in TABLE_ASSEMBLIES_BOM_DEFAULT_COLUMNS");
            }

            $ret[] = $enum;
        }

        return $ret;
    }

}
