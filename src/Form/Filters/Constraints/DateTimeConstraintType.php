<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Form\Filters\Constraints;

use App\DataTables\Filters\Constraints\DateTimeConstraint;
use App\DataTables\Filters\Constraints\NumberConstraint;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DateTimeConstraintType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => true,
            'data_class' => DateTimeConstraint::class,
            'text_suffix' => '', // A suffix which is attached as text-append to the input group. This can for example be used for units

            'value1_options' => [], // Options for the first value input
            'value2_options' => [], // Options for the second value input
            'input_type' => DateTimeType::class,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [
            '' => '',
            '=' => '=',
            '!=' => '!=',
            '<' => '<',
            '>' => '>',
            '<=' => '<=',
            '>=' => '>=',
            'filter.number_constraint.value.operator.BETWEEN' => 'BETWEEN',
        ];

        $builder->add('value1', $options['input_type'], array_merge_recursive([
            'label' => 'filter.datetime_constraint.value1',
            'attr' => [
                'placeholder' => 'filter.datetime_constraint.value1',
            ],
            'required' => false,
            'html5' => true,
            'widget' => 'single_text',
        ], $options['value1_options']));

        $builder->add('value2', $options['input_type'], array_merge_recursive([
            'label' => 'filter.datetime_constraint.value2',
            'attr' => [
                'placeholder' => 'filter.datetime_constraint.value2',
            ],
            'required' => false,
            'html5' => true,
            'widget' => 'single_text',
        ], $options['value2_options']));

        $builder->add('operator', ChoiceType::class, [
            'label' => 'filter.datetime_constraint.operator',
            'choices' => $choices,
            'required' => false,
        ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);

        $view->vars['text_suffix'] = $options['text_suffix'];
    }
}