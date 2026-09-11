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

namespace App\Form\OAuth;

use App\Entity\UserSystem\ApiTokenLevel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Used both for manually registering a new OAuth2 client and for editing an already-registered one (see
 * App\Controller\OAuth\OAuthClientAdminController), backed by App\Form\OAuth\OAuthClientData. The
 * "active" toggle only makes sense for a client that already exists, so it is only added in edit mode.
 *
 * The block prefix is emptied so field names stay "name", "redirect_uris", "scopes" (no "o_auth_client_type["
 * wrapper) - templates/tools/oauth_clients render the individual scope checkboxes by hand to keep the
 * elevated-scope warning icon, so they need to know the plain field name.
 */
class OAuthClientType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'];

        $builder->add('name', TextType::class, [
            'label' => 'oauth_clients.create.name',
            // OAuthClientData::$name is a non-nullable string - without this, an empty submission binds
            // null onto it instead of '', which PropertyAccessor rejects with a TypeError.
            'empty_data' => '',
            'attr' => [
                'maxlength' => 128,
                'autofocus' => !$isEdit,
            ],
        ]);

        $builder->add('redirect_uris', TextareaType::class, [
            'label' => 'oauth_clients.create.redirect_uris',
            'help' => 'oauth_clients.create.redirect_uris.help',
            // Same reasoning as "name" above - OAuthClientData::$redirect_uris is a non-nullable string.
            'empty_data' => '',
            'attr' => [
                'rows' => 3,
                'placeholder' => 'https://client.example.com/callback',
            ],
        ]);

        // Scope choices are plain scope-name strings (matching what
        // App\Services\OAuth\OAuthClientAdminManager::createClient()/updateClient() and
        // App\Form\OAuth\OAuthClientData::$scopes expect) rather than ApiTokenLevel cases themselves:
        // ChoiceType's "choice_value" only controls the string used for the submitted HTML value, the
        // bound form/model data is always the matched "choices" entry, so using the enum cases directly
        // here would bind ApiTokenLevel instances onto OAuthClientData::$scopes instead of strings.
        $levelsByScopeName = [];
        foreach (ApiTokenLevel::cases() as $level) {
            $levelsByScopeName[strtolower($level->name)] = $level;
        }

        $builder->add('scopes', ChoiceType::class, [
            'label' => 'oauth_clients.create.scopes',
            'help' => 'oauth_clients.create.scopes.elevated_help',
            'expanded' => true,
            'multiple' => true,
            'choices' => array_combine(array_keys($levelsByScopeName), array_keys($levelsByScopeName)),
            'choice_label' => static fn (string $value): string => $levelsByScopeName[$value]->getTranslationKey(),
            'choice_attr' => static fn (string $value): array => [
                'data-elevated' => $levelsByScopeName[$value]->value > ApiTokenLevel::EDIT->value ? '1' : '0',
            ],
        ]);

        if ($isEdit) {
            $builder->add('active', CheckboxType::class, [
                'label' => 'oauth_clients.edit.active',
                'help' => 'oauth_clients.edit.active.help',
                'required' => false,
            ]);
        }

        $builder->add('submit', SubmitType::class, [
            'label' => $isEdit ? 'oauth_clients.edit.submit' : 'oauth_clients.create.submit',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OAuthClientData::class,
            'is_edit' => false,
        ]);
        $resolver->setAllowedTypes('is_edit', 'bool');
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
