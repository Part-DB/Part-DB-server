<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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

use App\Entity\Attachments\Attachment;
use App\Entity\Base\AbstractNamedDBElement;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Form\AttachmentFormType;
use App\Form\ParameterType;
use App\Form\Type\MasterPictureAttachmentType;
use App\Form\Type\StructuralEntityType;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use function get_class;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;

class BaseEntityAdminForm extends AbstractType
{
    protected $security;
    protected $params;

    public function __construct(Security $security, ParameterBagInterface $params)
    {
        $this->security = $security;
        $this->params = $params;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setRequired('attachment_class');
        $resolver->setRequired('parameter_class');
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var AbstractStructuralDBElement $entity */
        $entity = $options['data'];
        $is_new = null === $entity->getID();

        $builder
            ->add('name', TextType::class, [
                'empty_data' => '',
                'label' => 'name.label',
                'attr' => [
                    'placeholder' => 'part.name.placeholder',
                ],
                'disabled' => ! $this->security->isGranted($is_new ? 'create' : 'edit', $entity),
            ])

            ->add('parent', StructuralEntityType::class, [
                'class' => get_class($entity),
                'required' => false,
                'label' => 'parent.label',
                'disabled' => ! $this->security->isGranted($is_new ? 'create' : 'move', $entity),
            ])

            ->add('not_selectable', CheckboxType::class, [
                'required' => false,
                'label' => 'entity.edit.not_selectable',
                'help' => 'entity.edit.not_selectable.help',
                'label_attr' => [
                    'class' => 'checkbox-custom',
                ],
                'disabled' => ! $this->security->isGranted($is_new ? 'create' : 'edit', $entity),
            ])

            ->add('comment', CKEditorType::class, [
                'required' => false,
                'empty_data' => '',
                'label' => 'comment.label',
                'attr' => [
                    'rows' => 4,
                ],
                'help' => 'bbcode.hint',
                'disabled' => ! $this->security->isGranted($is_new ? 'create' : 'edit', $entity),
            ]);

        $this->additionalFormElements($builder, $options, $entity);

        //Attachment section
        $builder->add('attachments', CollectionType::class, [
            'entry_type' => AttachmentFormType::class,
            'allow_add' => true,
            'allow_delete' => true,
            'label' => false,
            'reindex_enable' => true,
            'disabled' => ! $this->security->isGranted($is_new ? 'create' : 'edit', $entity),
            'entry_options' => [
                'data_class' => $options['attachment_class'],
            ],
            'by_reference' => false,
        ]);

        $builder->add('master_picture_attachment', MasterPictureAttachmentType::class, [
            'required' => false,
            'disabled' => ! $this->security->isGranted($is_new ? 'create' : 'edit', $entity),
            'label' => 'part.edit.master_attachment',
            'entity' => $entity,
        ]);

        $builder->add('log_comment', TextType::class, [
            'label' => 'edit.log_comment',
            'mapped' => false,
            'required' => false,
            'empty_data' => null,
        ]);

        $builder->add('parameters', CollectionType::class, [
            'entry_type' => ParameterType::class,
            'allow_add' => $this->security->isGranted($is_new ? 'create' : 'edit', $entity),
            'allow_delete' => $this->security->isGranted($is_new ? 'create' : 'edit', $entity),
            'disabled' => ! $this->security->isGranted($is_new ? 'create' : 'edit', $entity),
            'reindex_enable' => true,
            'label' => false,
            'by_reference' => false,
            'prototype_data' => new $options['parameter_class'](),
            'entry_options' => [
                'data_class' => $options['parameter_class'],
            ],
        ]);

        //Buttons
        $builder->add('save', SubmitType::class, [
            'label' => $is_new ? 'entity.create' : 'entity.edit.save',
            'attr' => [
                'class' => $is_new ? 'btn-success' : '',
            ],
            'disabled' => ! $this->security->isGranted($is_new ? 'create' : 'edit', $entity),
        ])
            ->add('reset', ResetType::class, [
                'label' => 'entity.edit.reset',
                'disabled' => ! $this->security->isGranted($is_new ? 'create' : 'edit', $entity),
            ]);
    }

    protected function additionalFormElements(FormBuilderInterface $builder, array $options, AbstractNamedDBElement $entity): void
    {
        //Empty for Base
    }
}
