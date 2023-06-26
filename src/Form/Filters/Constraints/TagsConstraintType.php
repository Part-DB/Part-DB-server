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

use App\DataTables\Filters\Constraints\Part\TagsConstraint;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TagsConstraintType extends AbstractType
{
    public function __construct(protected UrlGeneratorInterface $urlGenerator)
    {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => true,
            'data_class' => TagsConstraint::class,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [
            '' => '',
            'filter.tags_constraint.operator.ANY' => 'ANY',
            'filter.tags_constraint.operator.ALL' => 'ALL',
            'filter.tags_constraint.operator.NONE' => 'NONE'
        ];

        $builder->add('value', SearchType::class, [
            'attr' => [
                'class' => 'tagsinput',
                'data-controller' => 'elements--tagsinput',
                'data-autocomplete' => $this->urlGenerator->generate('typeahead_tags', ['query' => '__QUERY__']),
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
}
