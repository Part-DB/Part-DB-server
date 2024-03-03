<?php

declare(strict_types=1);

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

use App\DataTables\Filters\Constraints\TextConstraint;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TextConstraintType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => true,
            'data_class' => TextConstraint::class,
            'text_suffix' => '', // An suffix which is attached as text-append to the input group. This can for example be used for units
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [
            '' => '',
            'filter.text_constraint.value.operator.EQ' => '=',
            'filter.text_constraint.value.operator.NEQ' => '!=',
            'filter.text_constraint.value.operator.STARTS' => 'STARTS',
            'filter.text_constraint.value.operator.ENDS' => 'ENDS',
            'filter.text_constraint.value.operator.CONTAINS' => 'CONTAINS',
            'filter.text_constraint.value.operator.LIKE' => 'LIKE',
            'filter.text_constraint.value.operator.REGEX' => 'REGEX',
        ];

        $builder->add('value', SearchType::class, [
            'attr' => [
                'placeholder' => 'filter.text_constraint.value',
            ],
            'required' => false,
            'empty_data' => '',
        ]);


        $builder->add('operator', ChoiceType::class, [
            'label' => 'filter.text_constraint.operator',
            'choices' => $choices,
            'required' => false,
        ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        $view->vars['text_suffix'] = $options['text_suffix'];
    }
}
