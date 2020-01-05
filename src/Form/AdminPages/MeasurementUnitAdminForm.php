<?php

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Form\AdminPages;

use App\Entity\Base\NamedDBElement;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class MeasurementUnitAdminForm extends BaseEntityAdminForm
{
    protected function additionalFormElements(FormBuilderInterface $builder, array $options, NamedDBElement $entity): void
    {
        $is_new = null === $entity->getID();

        $builder->add('is_integer', CheckboxType::class, [
            'required' => false,
            'label' => 'measurement_unit.edit.is_integer',
            'help' => 'measurement_unit.edit.is_integer.help',
            'label_attr' => [
                'class' => 'checkbox-custom',
            ],
            'disabled' => ! $this->security->isGranted($is_new ? 'create' : 'edit', $entity),
        ]);

        $builder->add('use_si_prefix', CheckboxType::class, [
            'required' => false,
            'label' => 'measurement_unit.edit.use_si_prefix',
            'help' => 'measurement_unit.edit.use_si_prefix.help',
            'label_attr' => [
                'class' => 'checkbox-custom',
            ],
            'disabled' => ! $this->security->isGranted($is_new ? 'create' : 'edit', $entity),
        ]);

        $builder->add('unit', TextType::class, [
            'required' => false,
            'label' => 'measurement_unit.edit.unit_symbol',
            'attr' => [
                'placeholder' => 'measurement_unit.edit.unit_symbol.placeholder',
            ],
            'disabled' => ! $this->security->isGranted($is_new ? 'create' : 'edit', $entity),
        ]);
    }
}
