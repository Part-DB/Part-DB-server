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

use App\Entity\Base\StructuralDBElement;
use App\Form\Type\StructuralEntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Security;

class MassCreationForm extends AbstractType
{
    protected $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $data = $options['data'];

        //Disable import if user is not allowed to create elements.
        $entity = new $data['entity_class']();
        $perm_name = 'create';
        $disabled = ! $this->security->isGranted($perm_name, $entity);

        $builder
            ->add('lines', TextareaType::class, ['data' => '',
                'label' => 'mass_creation.lines',
                'disabled' => $disabled, 'required' => true,
                'attr' => [
                    'placeholder' => 'mass_creation.lines.placeholder',
                    'rows' => 10,
                ],
            ]);
        if ($entity instanceof StructuralDBElement) {
            $builder->add('parent', StructuralEntityType::class, [
                'class' => $data['entity_class'],
                'required' => false,
                'label' => 'parent.label',
                'disabled' => $disabled, ]);
        }

        //Buttons
        $builder->add('create', SubmitType::class, [
            'label' => 'entity.mass_creation.btn',
            'disabled' => $disabled,
        ]);
    }
}
