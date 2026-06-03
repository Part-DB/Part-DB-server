<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);


namespace App\Form\Security;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

class LoginFormType extends AbstractType
{
    public function buildForm(\Symfony\Component\Form\FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('_username', TextType::class, [
                'label' => t('login.username.label'),
                'attr' => [
                    'autofocus' => 'autofocus',
                    'autocomplete' => 'username',
                    'placeholder' => t('login.username.placeholder'),
                ]
            ])
            ->add('_password', PasswordType::class, [
                'label' => t('login.password.label'),
                'attr' => [
                    'autocomplete' => 'current-password',
                    'placeholder' => t('login.password.placeholder'),
                ]
            ])
            ->add('_remember_me', CheckboxType::class, [
                'label' => t('login.rememberme'),
                'required' => false,
            ])
            ->add('submit', \Symfony\Component\Form\Extension\Core\Type\SubmitType::class, [
                'label' => t('login.btn'),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // This ensures CSRF protection is active for the login
            'csrf_protection' => true,
            'csrf_field_name' => '_csrf_token',
            'csrf_token_id'   => 'authenticate',
            'attr' => [
                'data-turbo' => 'false', // Disable Turbo for the login form to ensure proper redirection after login
            ]
        ]);
    }

    public function getBlockPrefix(): string
    {
        // This removes the "login_form_" prefix from field names
        // so that Security can find "_username" directly.
        return '';
    }
}
