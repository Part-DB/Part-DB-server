<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

declare(strict_types=1);

namespace App\Form\AdminPages;

use App\Entity\Base\AbstractNamedDBElement;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class MeasurementUnitAdminForm extends BaseEntityAdminForm
{
    protected function additionalFormElements(FormBuilderInterface $builder, array $options, AbstractNamedDBElement $entity): void
    {
        $is_new = null === $entity->getID();

        $builder->add('is_integer', CheckboxType::class, [
            'required' => false,
            'label' => 'measurement_unit.edit.is_integer',
            'help' => 'measurement_unit.edit.is_integer.help',
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity),
        ]);

        $builder->add('use_si_prefix', CheckboxType::class, [
            'required' => false,
            'label' => 'measurement_unit.edit.use_si_prefix',
            'help' => 'measurement_unit.edit.use_si_prefix.help',
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity),
        ]);

        $builder->add('unit', TextType::class, [
            'required' => false,
            'label' => 'measurement_unit.edit.unit_symbol',
            'attr' => [
                'placeholder' => 'measurement_unit.edit.unit_symbol.placeholder',
            ],
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity),
        ]);
    }
}
