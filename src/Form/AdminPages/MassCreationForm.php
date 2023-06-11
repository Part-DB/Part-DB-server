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

use App\Entity\Base\AbstractStructuralDBElement;
use App\Form\Type\StructuralEntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Security;

class MassCreationForm extends AbstractType
{
    public function __construct(protected \Symfony\Bundle\SecurityBundle\Security $security)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $data = $options['data'];

        //Disable import if user is not allowed to create elements.
        $entity = new $data['entity_class']();
        $perm_name = 'create';
        $disabled = !$this->security->isGranted($perm_name, $entity);

        $builder
            ->add('lines', TextareaType::class, [
                'data' => '',
                'label' => 'mass_creation.lines',
                'disabled' => $disabled,
                'required' => true,
                'attr' => [
                    'placeholder' => 'mass_creation.lines.placeholder',
                    'rows' => 10,
                ],
            ]);
        if ($entity instanceof AbstractStructuralDBElement) {
            $builder->add('parent', StructuralEntityType::class, [
                'class' => $data['entity_class'],
                'required' => false,
                'label' => 'parent.label',
                'disabled' => $disabled,
            ]);
        }

        //Buttons
        $builder->add('create', SubmitType::class, [
            'label' => 'entity.mass_creation.btn',
            'disabled' => $disabled,
        ]);
    }
}
