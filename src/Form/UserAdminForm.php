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


use App\Entity\UserSystem\Group;
use App\Entity\Base\NamedDBElement;
use App\Entity\Base\StructuralDBElement;
use App\Form\Type\StructuralEntityType;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserAdminForm extends AbstractType
{

    protected $security;
    protected $trans;

    public function __construct(Security $security, TranslatorInterface $trans)
    {
        $this->security = $security;
        $this->trans = $trans;
    }


    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /** @var StructuralDBElement $entity */
        $entity = $options['data'];
        $is_new = $entity->getID() === null;

        $builder
            ->add('name', TextType::class, [
                'empty_data' => '',
                'label' =>  $this->trans->trans('user.username.label'),
                'attr' => ['placeholder' => $this->trans->trans('user.username.placeholder')],
                'disabled' => !$this->security->isGranted('edit_username', $entity),
            ])

            ->add('group', StructuralEntityType::class, [
                'class' => Group::class,
                'required' => false,
                'label' => $this->trans->trans('group.label'),
                'disable_not_selectable' => true,
                'disabled' => !$this->security->isGranted('change_group', $entity), ])

            ->add('first_name', TextType::class, [
                'empty_data' => '',
                'label' => $this->trans->trans('user.firstName.label'),
                'attr' => ['placeholder' => $this->trans->trans('user.firstName.placeholder')], 'required' => false,
                'disabled' => !$this->security->isGranted('edit_infos', $entity),
            ])

            ->add('last_name', TextType::class, [
                'empty_data' => '',
                'label' => $this->trans->trans('user.lastName.label'),
                'attr' => ['placeholder' => $this->trans->trans('user.lastName.placeholder')],
                'required' => false,
                'disabled' => !$this->security->isGranted('edit_infos', $entity),
            ])

            ->add('email', TextType::class, [
                'empty_data' => '',
                'label' => $this->trans->trans('user.email.label'),
                'attr' => ['placeholder' => $this->trans->trans('user.email.placeholder')],
                'required' => false,
                'disabled' => !$this->security->isGranted('edit_infos', $entity), ])


            ->add('department', TextType::class, [
                'empty_data' => '',
                'label' => $this->trans->trans('user.department.label'),
                'attr' => ['placeholder' => $this->trans->trans('user.department.placeholder')],
                'required' => false,
                'disabled' => !$this->security->isGranted('edit_infos', $entity),
            ])

        ;
        /*->add('comment', CKEditorType::class, ['required' => false,
            'label' => 'comment.label', 'attr' => ['rows' => 4], 'help' => 'bbcode.hint',
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity)]); */

        $this->additionalFormElements($builder, $options, $entity);

        //Buttons
        $builder->add('save', SubmitType::class, [
            'label' =>  $is_new ? $this->trans->trans('user.create') : $this->trans->trans('user.edit.save'),
            'attr' => ['class' => $is_new ? 'btn-success' : '']
        ])
            ->add('reset', ResetType::class, [
                'label' => $this->trans->trans('entity.edit.reset')
            ]);
    }

    protected function additionalFormElements(FormBuilderInterface $builder, array $options, NamedDBElement $entity)
    {
        //Empty for Base
    }
}