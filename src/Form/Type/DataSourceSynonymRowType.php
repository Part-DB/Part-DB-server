<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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

use App\Settings\SystemSettings\LocalizationSettings;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\LocaleType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Intl\Locales;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A single translation row: data source + language + translations (singular/plural).
 */
class DataSourceSynonymRowType extends AbstractType
{
    public function __construct(
        private readonly LocalizationSettings $localizationSettings,
        #[Autowire(param: 'partdb.locale_menu')] private readonly array $preferredLanguagesParam,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dataSource', ChoiceType::class, [
                //'label' => 'settings.behavior.data_source_synonyms.row_type.form.datasource',
                'label' => false,
                'choices' => $this->buildDataSourceChoices($options['data_sources']),
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(),
                ],
                'row_attr' => ['class' => 'mb-0'],
                'attr' => ['class' => 'form-select-sm']
            ])
            ->add('locale', LocaleType::class, [
                //'label' => 'settings.behavior.data_source_synonyms.row_type.form.locale',
                'label' => false,
                'required' => true,
                // Restrict to languages configured in the language menu: disable ChoiceLoader and provide explicit choices
                'choice_loader' => null,
                'choices' => $this->buildLocaleChoices(true),
                'preferred_choices' => $this->getPreferredLocales(),
                'constraints' => [
                    new Assert\NotBlank(),
                ],
                'row_attr' => ['class' => 'mb-0'],
                'attr' => ['class' => 'form-select-sm']
            ])
            ->add('translation_singular', TextType::class, [
                //'label' => 'settings.behavior.data_source_synonyms.row_type.form.translation_singular',
                'label' => false,
                'required' => true,
                'empty_data' => '',
                'constraints' => [
                    new Assert\NotBlank(),
                ],
                'row_attr' => ['class' => 'mb-0'],
                'attr' => ['class' => 'form-select-sm']
            ])
            ->add('translation_plural', TextType::class, [
                //'label' => 'settings.behavior.data_source_synonyms.row_type.form.translation_plural',
                'label' => false,
                'required' => true,
                'empty_data' => '',
                'constraints' => [
                    new Assert\NotBlank(),
                ],
                'row_attr' => ['class' => 'mb-0'],
                'attr' => ['class' => 'form-select-sm']
            ]);
    }

    private function buildDataSourceChoices(array $dataSources): array
    {
        $choices = [];
        foreach ($dataSources as $key => $label) {
            $choices[(string)$label] = (string)$key;
        }
        return $choices;
    }

    /**
     * Returns only locales configured in the language menu (settings) or falls back to the parameter.
     * Format: ['German (DE)' => 'de', ...]
     */
    private function buildLocaleChoices(bool $returnPossible = false): array
    {
        $locales = $this->getPreferredLocales();

        if ($returnPossible) {
            $locales = $this->getPossibleLocales();
        }

        $choices = [];
        foreach ($locales as $code) {
            $label = Locales::getName($code);
            $choices[$label . ' (' . strtoupper($code) . ')'] = $code;
        }
        return $choices;
    }

    /**
     * Source of allowed locales:
     * 1) LocalizationSettings->languageMenuEntries (if set)
     * 2) Fallback: parameter partdb.locale_menu
     */
    private function getPreferredLocales(): array
    {
        $fromSettings = $this->localizationSettings->languageMenuEntries ?? [];
        return !empty($fromSettings) ? array_values($fromSettings) : array_values($this->preferredLanguagesParam);
    }

    private function getPossibleLocales(): array
    {
        return array_values($this->preferredLanguagesParam);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('data_sources');
        $resolver->setAllowedTypes('data_sources', 'array');
    }
}
