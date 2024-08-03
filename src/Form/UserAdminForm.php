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

namespace App\Form;

use App\Form\Type\LocaleSelectType;
use Symfony\Bundle\SecurityBundle\Security;
use App\Entity\Base\AbstractNamedDBElement;
use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\User;
use App\Form\Permissions\PermissionsType;
use App\Form\Type\CurrencyEntityType;
use App\Form\Type\MasterPictureAttachmentType;
use App\Form\Type\RichTextEditorType;
use App\Form\Type\StructuralEntityType;
use App\Form\Type\ThemeChoiceType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

class UserAdminForm extends AbstractType
{
    public function __construct(protected Security $security)
    {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver); // TODO: Change the autogenerated stub
        $resolver->setRequired('attachment_class');
        $resolver->setDefault('parameter_class', false);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var User $entity */
        $entity = $options['data'];
        $is_new = null === $entity->getID();

        $builder
            ->add('name', TextType::class, [
                'empty_data' => '',
                'label' => 'user.username.label',
                'attr' => [
                    'placeholder' => 'user.username.placeholder',
                ],
                'disabled' => !$this->security->isGranted('edit_username', $entity),
            ])

            ->add('group', StructuralEntityType::class, [
                'class' => Group::class,
                'required' => false,
                'label' => 'group.label',
                'disable_not_selectable' => true,
                'disabled' => !$this->security->isGranted('edit_permissions', $entity),
            ])

            ->add('first_name', TextType::class, [
                'empty_data' => '',
                'label' => 'user.firstName.label',
                'attr' => [
                    'placeholder' => 'user.firstName.placeholder',
                ],
                'required' => false,
                'disabled' => !$this->security->isGranted('edit_infos', $entity),
            ])

            ->add('last_name', TextType::class, [
                'empty_data' => '',
                'label' => 'user.lastName.label',
                'attr' => [
                    'placeholder' => 'user.lastName.placeholder',
                ],
                'required' => false,
                'disabled' => !$this->security->isGranted('edit_infos', $entity),
            ])

            ->add('email', TextType::class, [
                'empty_data' => '',
                'label' => 'user.email.label',
                'attr' => [
                    'placeholder' => 'user.email.placeholder',
                ],
                'required' => false,
                'disabled' => !$this->security->isGranted('edit_infos', $entity),
            ])
            ->add('showEmailOnProfile', CheckboxType::class, [
                'required' => false,
                'label' => 'user.show_email_on_profile.label',
                'disabled' => !$this->security->isGranted('edit_infos', $entity),
            ])
            ->add('department', TextType::class, [
                'empty_data' => '',
                'label' => 'user.department.label',
                'attr' => [
                    'placeholder' => 'user.department.placeholder',
                ],
                'required' => false,
                'disabled' => !$this->security->isGranted('edit_infos', $entity),
            ])
            ->add('aboutMe', RichTextEditorType::class, [
                'required' => false,
                'empty_data' => '',
                'label' => 'user.aboutMe.label',
                'attr' => [
                    'rows' => 4,
                ],
                'mode' => 'markdown-full',
                'disabled' => !$this->security->isGranted('edit_infos', $entity),
            ])

            //Config section
            ->add('language', LocaleSelectType::class, [
                'required' => false,
                'placeholder' => 'user_settings.language.placeholder',
                'label' => 'user.language_select',
                'disabled' => !$this->security->isGranted('change_user_settings', $entity),
            ])
            ->add('timezone', TimezoneType::class, [
                'required' => false,
                'placeholder' => 'user_settings.timezone.placeholder',
                'label' => 'user.timezone.label',
                'preferred_choices' => ['Europe/Berlin'],
                'disabled' => !$this->security->isGranted('change_user_settings', $entity),
            ])
            ->add('theme', ThemeChoiceType::class, [
                'required' => false,
                'label' => 'user.theme.label',
                'disabled' => !$this->security->isGranted('change_user_settings', $entity),
            ])
            ->add('currency', CurrencyEntityType::class, [
                'required' => false,
                'label' => 'user.currency.label',
                'disabled' => !$this->security->isGranted('change_user_settings', $entity),
            ])

            ->add('new_password', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => [
                    'label' => 'user.settings.pw_new.label',
                    'password_estimator' => true,
                ],
                'second_options' => [
                    'label' => 'user.settings.pw_confirm.label',
                ],
                'invalid_message' => 'password_must_match',
                'required' => false,
                'mapped' => false,
                'disabled' => !$this->security->isGranted('set_password', $entity) || $entity->isSamlUser(),
                'constraints' => [new Length([
                    'min' => 6,
                    'max' => 128,
                ])],
            ])

            ->add('need_pw_change', CheckboxType::class, [
                'required' => false,
                'label' => 'user.edit.needs_pw_change',
                'disabled' => !$this->security->isGranted('set_password', $entity) || $entity->isSamlUser(),
            ])

            ->add('disabled', CheckboxType::class, [
                'required' => false,
                'label' => 'user.edit.user_disabled',
                'disabled' => !$this->security->isGranted('set_password', $entity)
                    || $entity === $this->security->getUser(),
            ])

            //Permission section
            ->add('permissions', PermissionsType::class, [
                'mapped' => false,
                'data' => $builder->getData(),
                'disabled' => !$this->security->isGranted('edit_permissions', $entity),
                'show_presets' => $this->security->isGranted('edit_permissions', $entity) && !$is_new,
            ])
        ;
        /*->add('comment', CKEditorType::class, ['required' => false,
            'label' => 'comment.label', 'attr' => ['rows' => 4], 'help' => 'bbcode.hint',
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity)]); */

        $this->additionalFormElements($builder, $options, $entity);

        //Attachment section
        $builder->add('attachments', CollectionType::class, [
            'entry_type' => AttachmentFormType::class,
            'allow_add' => true,
            'allow_delete' => true,
            'label' => false,
            'reindex_enable' => true,
            'entry_options' => [
                'data_class' => $options['attachment_class'],
            ],
            'by_reference' => false,
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit_infos', $entity),
        ]);

        $builder->add('master_picture_attachment', MasterPictureAttachmentType::class, [
            'required' => false,
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit_infos', $entity),
            'label' => 'part.edit.master_attachment',
            'entity' => $entity,
        ]);

        $builder->add('log_comment', TextType::class, [
            'label' => 'edit.log_comment',
            'mapped' => false,
            'required' => false,
            'empty_data' => null,
        ]);

        //Buttons
        $builder->add('save', SubmitType::class, [
            'label' => $is_new ? 'user.create' : 'user.edit.save',
            'attr' => [
                'class' => $is_new ? 'btn-success' : '',
            ],
        ])
            ->add('reset', ResetType::class, [
                'label' => 'entity.edit.reset',
            ]);
    }

    protected function additionalFormElements(FormBuilderInterface $builder, array $options, AbstractNamedDBElement $entity): void
    {
        //Empty for Base
    }
}
