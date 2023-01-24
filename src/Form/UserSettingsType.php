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

use App\Entity\UserSystem\User;
use App\Form\Type\CurrencyEntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\LanguageType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Validator\Constraints\File;

class UserSettingsType extends AbstractType
{
    protected Security $security;
    protected bool $demo_mode;

    public function __construct(Security $security, bool $demo_mode)
    {
        $this->security = $security;
        $this->demo_mode = $demo_mode;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'user.username.label',
                'disabled' => !$this->security->isGranted('edit_username', $options['data']) || $this->demo_mode,
            ])
            ->add('first_name', TextType::class, [
                'required' => false,
                'label' => 'user.firstName.label',
                'disabled' => !$this->security->isGranted('edit_infos', $options['data']) || $this->demo_mode,
            ])
            ->add('last_name', TextType::class, [
                'required' => false,
                'label' => 'user.lastName.label',
                'disabled' => !$this->security->isGranted('edit_infos', $options['data']) || $this->demo_mode,
            ])
            ->add('department', TextType::class, [
                'required' => false,
                'label' => 'user.department.label',
                'disabled' => !$this->security->isGranted('edit_infos', $options['data']) || $this->demo_mode,
            ])
            ->add('email', EmailType::class, [
                'required' => false,
                'label' => 'user.email.label',
                'disabled' => !$this->security->isGranted('edit_infos', $options['data']) || $this->demo_mode,
            ])
            ->add('avatar_file', FileType::class, [
                'label' => 'user_settings.change_avatar.label',
                'mapped' => false,
                'required' => false,
                'disabled' => !$this->security->isGranted('edit_infos', $options['data']) || $this->demo_mode,
                'attr' => [
                    'accept' => 'image/*',
                ],
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                    ]),
                ],
            ])
            ->add('language', LanguageType::class, [
                'disabled' => $this->demo_mode,
                'required' => false,
                'attr' => [
                    'data-controller' => 'elements--selectpicker',
                    'data-live-search' => true,
                ],
                'placeholder' => 'user_settings.language.placeholder',
                'label' => 'user.language_select',
                'preferred_choices' => ['en', 'de'],
            ])
            ->add('timezone', TimezoneType::class, [
                'disabled' => $this->demo_mode,
                'required' => false,
                'attr' => [
                    'data-controller' => 'elements--selectpicker',
                    'data-live-search' => true,
                ],
                'placeholder' => 'user_settings.timezone.placeholder',
                'label' => 'user.timezone.label',
                'preferred_choices' => ['Europe/Berlin'],
            ])
            ->add('theme', ChoiceType::class, [
                'disabled' => $this->demo_mode,
                'required' => false,
                'attr' => [
                    'data-controller' => 'elements--selectpicker',
                ],
                'choice_translation_domain' => false,
                'choices' => User::AVAILABLE_THEMES,
                'choice_label' => static function ($entity, $key, $value) {
                    return $value;
                },
                'placeholder' => 'user_settings.theme.placeholder',
                'label' => 'user.theme.label',
            ])
            ->add('currency', CurrencyEntityType::class, [
                'disabled' => $this->demo_mode,
                'required' => false,
                'label' => 'user.currency.label',
            ])

            //Buttons
            ->add('save', SubmitType::class, ['label' => 'save'])
            ->add('reset', ResetType::class, ['label' => 'reset']);

        //Add the remove_avatar button if the user has an avatar (we have to add this dynamically)
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (PreSetDataEvent $event) {
            $data = $event->getData();
            if (!$data instanceof User) {
                return;
            }
            $form = $event->getForm();

            //if ($data->getMasterPictureAttachment()) {
                $form->add('remove_avatar', SubmitType::class, [
                    'label' => 'user_settings.remove_avatar.label',
                    'disabled' => !$this->security->isGranted('edit_infos', $data) || $this->demo_mode,
                    'attr' => [
                        'class' => 'btn btn-link',
                    ],
                ]);
            //}
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
