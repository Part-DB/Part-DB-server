<?php
/**
 *
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/
 *
 * part-db version 0.2+
 * Copyright (C) 2009 K. Jacobs and others (see authors.php)
 * http://code.google.com/p/part-db/
 *
 * Part-DB Version 0.4+
 * Copyright (C) 2016 - 2019 Jan Böhmer
 * https://github.com/jbtronics
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
 *
 */

namespace App\Form\AdminPages;


use App\Entity\Base\NamedDBElement;
use App\Form\AdminPages\BaseEntityAdminForm;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class MeasurementUnitAdminForm extends BaseEntityAdminForm
{
    protected function additionalFormElements(FormBuilderInterface $builder, array $options, NamedDBElement $entity)
    {
        $is_new = $entity->getID() === null;

        $builder->add('is_integer', CheckboxType::class, ['required' => false,
            'label' => 'measurement_unit.is_integer.label', 'help' => 'measurement_unit.is_integer.help',
            'label_attr'=> ['class' => 'checkbox-custom'],
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity)]);

        $builder->add('use_si_prefix', CheckboxType::class, ['required' => false,
            'label' => 'measurement_unit.use_si_prefix.label', 'help' => 'measurement_unit.use_si_prefix.help',
            'label_attr'=> ['class' => 'checkbox-custom'],
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity)]);

        $builder->add('unit', TextType::class, ['required' => false,
            'label' => 'measurement_unit.unit.label', 'attr' => ['placeholder' => 'measurement_unit.unit.placeholder'],
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity)]);

    }
}