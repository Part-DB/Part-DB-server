<?php

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace App\Form;

use App\Entity\Parameters\AbstractParameter;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ParameterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, [
            'empty_data' => '',
            'attr' => [
                'placeholder' => 'parameters.name.placeholder',
                'class' => 'form-control-sm',
            ],
        ]);
        $builder->add('symbol', TextType::class, [
            'required' => false,
            'empty_data' => '',
            'attr' => [
                'placeholder' => 'parameters.symbol.placeholder',
                'class' => 'form-control-sm',
                'style' => 'max-width: 15ch;',
            ],
        ]);
        $builder->add('value_text', TextType::class, [
            'required' => false,
            'empty_data' => '',
            'attr' => [
                'placeholder' => 'parameters.text.placeholder',
                'class' => 'form-control-sm',
            ],
        ]);

        $builder->add('value_max', NumberType::class, [
            'required' => false,
            'html5' => true,
            'attr' => [
                'step' => 'any',
                'placeholder' => 'parameters.max.placeholder',
                'class' => 'form-control-sm',
                'style' => 'max-width: 15ch;',
            ],
        ]);
        $builder->add('value_min', NumberType::class, [
            'required' => false,
            'html5' => true,
            'attr' => [
                'step' => 'any',
                'placeholder' => 'parameters.min.placeholder',
                'class' => 'form-control-sm',
                'style' => 'max-width: 15ch;',
            ],
        ]);
        $builder->add('value_typical', NumberType::class, [
            'required' => false,
            'html5' => true,
            'attr' => [
                'step' => 'any',
                'placeholder' => 'parameters.typical.placeholder',
                'class' => 'form-control-sm',
                'style' => 'max-width: 15ch;',
            ],
        ]);
        $builder->add('unit', TextType::class, [
            'required' => false,
            'empty_data' => '',
            'attr' => [
                'placeholder' => 'parameters.unit.placeholder',
                'class' => 'form-control-sm',
                'style' => 'max-width: 8ch;',
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AbstractParameter::class,
        ]);
    }
}
