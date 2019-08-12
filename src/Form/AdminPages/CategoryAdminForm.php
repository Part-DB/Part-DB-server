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

class CategoryAdminForm extends BaseEntityAdminForm
{
    protected function additionalFormElements(FormBuilderInterface $builder, array $options, NamedDBElement $entity)
    {
        $is_new = $entity->getID() === null;

        $builder->add('disable_footprints', CheckboxType::class, ['required' => false,
            'label' => 'disable_footprints.label', 'help' => 'disable_footprints.help', 'label_attr'=> ['class' => 'checkbox-custom'],
                'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity)]);

        $builder->add('disable_manufacturers', CheckboxType::class, ['required' => false,
            'label' => 'disable_manufacturers.label', 'help' => 'disable_manufacturers.help', 'label_attr'=> ['class' => 'checkbox-custom'],
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity)]);

        $builder->add('disable_autodatasheets', CheckboxType::class, ['required' => false,
            'label' => 'disable_autodatasheets.label', 'help' => 'disable_autodatasheets.help', 'label_attr'=> ['class' => 'checkbox-custom'],
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity)]);

        $builder->add('disable_properties', CheckboxType::class, ['required' => false,
            'label' => 'disable_properties.label', 'help' => 'disable_properties.help', 'label_attr'=> ['class' => 'checkbox-custom'],
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity)]);

        $builder->add('partname_hint', TextType::class, ['required' => false, 'empty_data' => '',
            'label' => 'disable_manufacturers.label', 'attr' => ['placeholder' => 'disable_manufacturers.placeholder'],
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity)]);

        $builder->add('partname_regex', TextType::class, ['required' => false, 'empty_data' => '',
            'label' => 'partname_regex.label', 'attr' => ['placeholder' => 'partname_regex.placeholder'],
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity)]);

        $builder->add('default_description', TextType::class, ['required' => false, 'empty_data' => '',
            'label' => 'default_description.label', 'attr' => ['placeholder' => 'default_description.placeholder'],
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity)]);

        $builder->add('default_comment', TextType::class, ['required' => false, 'empty_data' => '',
            'label' => 'default_description.label', 'attr' => ['placeholder' => 'default_comment.placeholder'],
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity)]);
    }
}