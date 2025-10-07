<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Form\InfoProviderSystem;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;

class PartProviderConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('part_id', HiddenType::class);

        $builder->add('search_field', ChoiceType::class, [
            'label' => 'info_providers.bulk_search.search_field',
            'choices' => [
                'info_providers.bulk_search.field.mpn' => 'mpn',
                'info_providers.bulk_search.field.name' => 'name',
                'info_providers.bulk_search.field.digikey_spn' => 'digikey_spn',
                'info_providers.bulk_search.field.mouser_spn' => 'mouser_spn',
                'info_providers.bulk_search.field.lcsc_spn' => 'lcsc_spn',
                'info_providers.bulk_search.field.farnell_spn' => 'farnell_spn',
            ],
            'expanded' => false,
            'multiple' => false,
        ]);

        $builder->add('providers', ProviderSelectType::class, [
            'label' => 'info_providers.bulk_search.providers',
            'help' => 'info_providers.bulk_search.providers.help',
        ]);
    }
}