<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2025 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class APIKeyType extends AbstractType
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function getParent(): string
    {
        return PasswordType::class;
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $viewData = $form->getViewData();

        //If the field is disabled, show the redacted API key
        if ($options['disabled'] ?? false) {
            if ($viewData === null || $viewData === '') {
                $view->vars['value'] = $viewData;
            } else {

                $view->vars['value'] = self::redact((string)$viewData) . ' (' . $this ->translator->trans("form.apikey.redacted") . ')';
            }
        } else { //Otherwise, show the actual value
            $view->vars['value'] = $viewData;
        }
    }

    public static function redact(string $apiKey): string
    {
        //Show only the last 2 characters of the API key if it is long enough (more than 16 characters)
        //Replace all other characters with dots
        if (strlen($apiKey) > 16) {
            return str_repeat('*', strlen($apiKey) - 2) . substr($apiKey, -2);
        }

        return str_repeat('*', strlen($apiKey));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'always_empty' => false,
            'toggle' => true,
            'empty_data' => null,
            'attr' => ['autocomplete' => 'off'],
        ]);
    }
}
