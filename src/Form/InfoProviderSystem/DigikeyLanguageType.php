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


namespace App\Form\InfoProviderSystem;

use App\Settings\InfoProviderSystem\DigikeySettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Intl\Languages;
use Symfony\Component\Intl\Locales;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A select field, which only offers the languages supported by the Digikey API.
 * Digikey uses non-standard codes for chinese (zhs, zht), so we map them to the corresponding locales for the labels.
 */
class DigikeyLanguageType extends AbstractType
{
    private const NON_ISO_CODES = [
        'zhs' => 'zh_Hans',
        'zht' => 'zh_Hant',
    ];

    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choices' => array_combine(DigikeySettings::SUPPORTED_LANGUAGES, DigikeySettings::SUPPORTED_LANGUAGES),
            //Labels are resolved lazily, so that they are rendered in the current request locale
            'choice_label' => static fn(string $value): string => isset(self::NON_ISO_CODES[$value])
                ? Locales::getName(self::NON_ISO_CODES[$value])
                : Languages::getName($value),
            'choice_translation_domain' => false,
        ]);
    }
}
