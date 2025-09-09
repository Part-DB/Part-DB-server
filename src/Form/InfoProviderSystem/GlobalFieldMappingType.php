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
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GlobalFieldMappingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $fieldChoices = $options['field_choices'] ?? [];

        $builder->add('field_mappings', CollectionType::class, [
            'entry_type' => FieldToProviderMappingType::class,
            'entry_options' => [
                'label' => false,
                'field_choices' => $fieldChoices,
            ],
            'allow_add' => true,
            'allow_delete' => true,
            'prototype' => true,
            'label' => false,
        ]);

        $builder->add('prefetch_details', CheckboxType::class, [
            'label' => 'info_providers.bulk_import.prefetch_details',
            'required' => false,
            'help' => 'info_providers.bulk_import.prefetch_details_help',
        ]);

        $builder->add('submit', SubmitType::class, [
            'label' => 'info_providers.bulk_import.search.submit'
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'field_choices' => [],
        ]);
    }
}