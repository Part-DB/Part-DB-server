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

namespace App\Form;


use App\Entity\NamedDBElement;
use App\Entity\StructuralDBElement;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Security;

class BaseEntityAdminForm extends AbstractType
{

    protected $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }


    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /** @var StructuralDBElement $entity */
        $entity = $options['data'];
        $is_new = $entity->getID() === null;

        $builder
            ->add('name', TextType::class, ['empty_data' => '', 'label' => 'name.label',
                'attr' => ['placeholder' => 'part.name.placeholder'],
                'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity), ])

            ->add('parent', EntityType::class, ['class' => get_class($entity), 'choice_label' => 'full_path',
                'attr' => ['class' => 'selectpicker', 'data-live-search' => true], 'required' => false, 'label' => 'parent.label',
                'disabled' => !$this->security->isGranted($is_new ? 'create' : 'move', $entity), ])

            ->add('comment', CKEditorType::class, ['required' => false,
                'label' => 'comment.label', 'attr' => ['rows' => 4], 'help' => 'bbcode.hint',
                'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity)]);

            $this->additionalFormElements($builder, $options, $entity);

            //Buttons
            $builder->add('save', SubmitType::class, ['label' =>  $is_new ? 'entity.create' : 'entity.edit.save',
                'attr' => ['class' => $is_new ? 'btn-success' : ''],
                'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity)])
            ->add('reset', ResetType::class, ['label' => 'entity.edit.reset',
                'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity)]);
    }

    protected function additionalFormElements(FormBuilderInterface $builder, array $options, NamedDBElement $entity)
    {
        //Empty for Base
    }
}