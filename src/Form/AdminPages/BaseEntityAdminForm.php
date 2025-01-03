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

use App\Entity\PriceInformations\Currency;
use App\Entity\ProjectSystem\Project;
use App\Entity\UserSystem\Group;
use Symfony\Bundle\SecurityBundle\Security;
use App\Entity\Base\AbstractNamedDBElement;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\LabelSystem\LabelProfile;
use App\Form\AttachmentFormType;
use App\Form\ParameterType;
use App\Form\Type\MasterPictureAttachmentType;
use App\Form\Type\RichTextEditorType;
use App\Form\Type\StructuralEntityType;
use App\Services\LogSystem\EventCommentNeededHelper;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BaseEntityAdminForm extends AbstractType
{
    public function __construct(protected Security $security, protected EventCommentNeededHelper $eventCommentNeededHelper)
    {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setRequired('attachment_class');
        $resolver->setRequired('parameter_class');
        $resolver->setAllowedTypes('parameter_class', ['string', 'null']);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var AbstractStructuralDBElement|LabelProfile|AbstractNamedDBElement $entity */
        $entity = $options['data'];
        $is_new = null === $entity->getID();

        $builder
            ->add('name', TextType::class, [
                'empty_data' => '',
                'label' => 'name.label',
                'attr' => [
                    'placeholder' => 'part.name.placeholder',
                ],
                'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity),
            ]);

        if ($entity instanceof AbstractStructuralDBElement) {
            $builder->add(
                'parent',
                StructuralEntityType::class,
                [
                    'class' => $entity::class,
                    'required' => false,
                    'label' => 'parent.label',
                    'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity),
                ]
            )
                ->add(
                    'not_selectable',
                    CheckboxType::class,
                    [
                        'required' => false,
                        'label' => 'entity.edit.not_selectable',
                        'help' => 'entity.edit.not_selectable.help',
                        'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity),
                    ]
                );
        }
        if ($entity instanceof AbstractStructuralDBElement || $entity instanceof LabelProfile) {
            $builder->add(
                'comment',
                RichTextEditorType::class,
                [
                    'required' => false,
                    'empty_data' => '',
                    'label' => 'comment.label',
                    'attr' => [
                        'rows' => 4,
                    ],
                    'mode' => 'markdown-full',
                    'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity),
                ]
            );
        }

        if ($entity instanceof AbstractStructuralDBElement && !($entity instanceof Group || $entity instanceof Project || $entity instanceof Currency)) {
            $builder->add('alternative_names', TextType::class, [
                'required' => false,
                'label' => 'entity.edit.alternative_names.label',
                'help' => 'entity.edit.alternative_names.help',
                'empty_data' => null,
                'attr' => [
                    'class' => 'tagsinput',
                    'data-controller' => 'elements--tagsinput',
                ]
            ]);
        }

        $this->additionalFormElements($builder, $options, $entity);

        //Attachment section
        $builder->add('attachments', CollectionType::class, [
            'entry_type' => AttachmentFormType::class,
            'allow_add' => true,
            'allow_delete' => true,
            'label' => false,
            'reindex_enable' => true,
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity),
            'entry_options' => [
                'data_class' => $options['attachment_class'],
            ],
            'by_reference' => false,
        ]);

        $builder->add('master_picture_attachment', MasterPictureAttachmentType::class, [
            'required' => false,
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity),
            'label' => 'part.edit.master_attachment',
            'entity' => $entity,
        ]);

        $builder->add('log_comment', TextType::class, [
            'label' => 'edit.log_comment',
            'mapped' => false,
            'required' => $this->eventCommentNeededHelper->isCommentNeeded($is_new ? 'datastructure_create': 'datastructure_edit'),
            'empty_data' => null,
        ]);

        if ($options['parameter_class']) {
            $prototype = new $options['parameter_class']();

            $builder->add(
                'parameters',
                CollectionType::class,
                [
                    'entry_type' => ParameterType::class,
                    'allow_add' => $this->security->isGranted($is_new ? 'create' : 'edit', $entity),
                    'allow_delete' => $this->security->isGranted($is_new ? 'create' : 'edit', $entity),
                    'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity),
                    'reindex_enable' => true,
                    'label' => false,
                    'by_reference' => false,
                    'prototype_data' => $prototype,
                    'entry_options' => [
                        'data_class' => $options['parameter_class'],
                    ],
                ]
            );
        }

        //Buttons
        $builder->add('save', SubmitType::class, [
            'label' => $is_new ? 'entity.create' : 'entity.edit.save',
            'attr' => [
                'class' => $is_new ? 'btn-success' : '',
            ],
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity),
        ])
            ->add('reset', ResetType::class, [
                'label' => 'entity.edit.reset',
                'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity),
            ]);
    }

    protected function additionalFormElements(FormBuilderInterface $builder, array $options, AbstractNamedDBElement $entity): void
    {
        //Empty for Base
    }
}
