<?php

declare(strict_types=1);

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

namespace App\Form\Filters\Constraints;

use App\DataTables\Filters\Constraints\Part\BulkImportPartStatusConstraint;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BulkImportPartStatusConstraintType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => true,
            'data_class' => BulkImportPartStatusConstraint::class,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $statusChoices = [
            'bulk_import.part_status.pending' => 'pending',
            'bulk_import.part_status.completed' => 'completed',
            'bulk_import.part_status.skipped' => 'skipped',
            'bulk_import.part_status.failed' => 'failed',
        ];

        $operatorChoices = [
            'filter.choice_constraint.operator.ANY' => 'ANY',
            'filter.choice_constraint.operator.NONE' => 'NONE',
        ];

        $builder->add('operator', ChoiceType::class, [
            'label' => 'filter.operator',
            'choices' => $operatorChoices,
            'required' => false,
        ]);

        $builder->add('values', ChoiceType::class, [
            'label' => 'part.filter.bulk_import_part_status',
            'choices' => $statusChoices,
            'required' => false,
            'multiple' => true,
            'attr' => [
                'data-controller' => 'elements--select-multiple',
            ]
        ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);
    }
}