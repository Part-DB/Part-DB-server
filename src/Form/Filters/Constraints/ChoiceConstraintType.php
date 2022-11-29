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

use App\DataTables\Filters\Constraints\ChoiceConstraint;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ChoiceConstraintType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('choices');
        $resolver->setAllowedTypes('choices', 'array');

        $resolver->setDefaults([
            'compound' => true,
            'data_class' => ChoiceConstraint::class,
        ]);

    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [
            '' => '',
            'filter.choice_constraint.operator.ANY' => 'ANY',
            'filter.choice_constraint.operator.NONE' => 'NONE',
        ];

        $builder->add('operator', ChoiceType::class, [
            'choices' => $choices,
            'required' => false,
        ]);

        $builder->add('value', ChoiceType::class, [
            'choices' => $options['choices'],
            'required' => false,
            'multiple' => true,
            'attr' => [
                'data-controller' => 'elements--select-multiple',
            ]
        ]);
    }

}