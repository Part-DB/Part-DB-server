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
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FieldToProviderMappingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $fieldChoices = $options['field_choices'] ?? [];

        $builder->add('field', ChoiceType::class, [
            'label' => 'info_providers.bulk_search.search_field',
            'choices' => $fieldChoices,
            'expanded' => false,
            'multiple' => false,
            'required' => false,
            'placeholder' => 'info_providers.bulk_search.field.select',
        ]);

        $builder->add('providers', ProviderSelectType::class, [
            'label' => 'info_providers.bulk_search.providers',
            'help' => 'info_providers.bulk_search.providers.help',
            'required' => false,
        ]);

        $builder->add('priority', IntegerType::class, [
            'label' => 'info_providers.bulk_search.priority',
            'help' => 'info_providers.bulk_search.priority.help',
            'required' => false,
            'data' => 1, // Default priority
            'attr' => [
                'min' => 1,
                'max' => 10,
                'class' => 'form-control-sm',
                'style' => 'width: 80px;'
            ]
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'field_choices' => [],
        ]);
    }
}