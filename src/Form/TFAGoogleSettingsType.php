<?php
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

namespace App\Form;


use App\Entity\UserSystem\User;
use App\Validator\Constraints\ValidGoogleAuthCode;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class TFAGoogleSettingsType extends AbstractType
{

    protected $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function(FormEvent $event) {
            $form = $event->getForm();
            /** @var User $user */
            $user = $event->getData();

            //Only show setup fields, when google authenticator is not enabled
            if(!$user->isGoogleAuthenticatorEnabled()) {
                $form->add(
                    'google_confirmation',
                    TextType::class,
                    [
                        'mapped' => false,
                        'attr' => ['maxlength' => '6', 'minlength' => '6', 'pattern' => '\d*', 'autocomplete' => 'off'],
                        'constraints' => [new ValidGoogleAuthCode()]
                    ]
                );

                $form->add(
                    'googleAuthenticatorSecret',
                    HiddenType::class,
                    [
                        'disabled' => false,
                    ]
                );

                $form->add('submit', SubmitType::class, [
                    'label' => $this->translator->trans('tfa_google.enable')
                ]);
            } else {
                $form->add('submit', SubmitType::class, [
                    'label' => $this->translator->trans('tfa_google.disable'),
                    'attr' => ['class' => 'btn-danger']
                ]);
            }
        });

        //$builder->add('cancel', ResetType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
                                   'data_class' => User::class,
                               ]);
    }
}