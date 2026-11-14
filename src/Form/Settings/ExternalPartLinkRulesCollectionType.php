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

namespace App\Form\Settings;

use App\Services\Tools\ExternalPartLinkIcon;
use App\Services\Tools\ExternalPartLinkSourceType;
use App\Services\Tools\ExternalPartLinkTemplateValidator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Ordered list of external part link definition rows.
 * Model data: array<int, array{name, source_type, source, icon, open_new_tab, enabled}> (as stored in settings)
 * View data: same shape, but with sourceType/icon as enum instances (as expected by the row's EnumType fields)
 */
class ExternalPartLinkRulesCollectionType extends AbstractType
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    private function toViewRows(array $modelValue): array
    {
        $out = [];
        foreach ($modelValue as $row) {
            if (!is_array($row)) {
                continue;
            }

            $sourceType = ExternalPartLinkSourceType::tryFrom((string) ($row['source_type'] ?? ''));
            $icon = ExternalPartLinkIcon::tryFrom((string) ($row['icon'] ?? ''));

            $out[] = [
                'name' => (string) ($row['name'] ?? ''),
                'sourceType' => $sourceType ?? ExternalPartLinkSourceType::TEMPLATE,
                'source' => (string) ($row['source'] ?? ''),
                'icon' => $icon ?? ExternalPartLinkIcon::EXTERNAL_LINK,
                'openNewTab' => (bool) ($row['open_new_tab'] ?? true),
                'enabled' => (bool) ($row['enabled'] ?? true),
            ];
        }

        return $out;
    }

    private function toModelRows(array $viewValue): array
    {
        $out = [];
        foreach ($viewValue as $row) {
            if (!is_array($row)) {
                continue;
            }

            $sourceType = $row['sourceType'] ?? null;
            $icon = $row['icon'] ?? null;
            $name = $row['name'] ?? null;
            $source = $row['source'] ?? null;

            if (!$sourceType instanceof ExternalPartLinkSourceType || !$icon instanceof ExternalPartLinkIcon
                || !is_string($name) || $name === '' || !is_string($source) || $source === '') {
                continue;
            }

            $out[] = [
                'name' => $name,
                'source_type' => $sourceType->value,
                'source' => $source,
                'icon' => $icon->value,
                'open_new_tab' => (bool) ($row['openNewTab'] ?? true),
                'enabled' => (bool) ($row['enabled'] ?? true),
            ];
        }

        return $out;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        //The model transformer's transform() already converts the model rows to view rows whenever data is set on
        //this form (including the very first time), so a PRE_SET_DATA listener doing the same conversion would run
        //it a second time on its own output - failing as soon as a row exists, since sourceType/icon are already
        //enum instances by then instead of the strings toViewRows() expects.
        $builder->addModelTransformer(new CallbackTransformer(
            fn (array $modelValue) => $this->toViewRows($modelValue),
            fn (array $viewValue) => $this->toModelRows($viewValue),
        ));

        //Reject templates using an unknown placeholder or that don't resolve to a valid https URL
        //with sample data, giving a concrete error message per row (spec: "Fehlerhafte Templates
        //werden vor dem Speichern mit einer konkreten Meldung abgelehnt.")
        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event): void {
            $form = $event->getForm();
            $rows = $event->getData();

            if (!is_array($rows)) {
                return;
            }

            foreach ($rows as $idx => $row) {
                if (!is_array($row) || !($row['sourceType'] ?? null) instanceof ExternalPartLinkSourceType) {
                    continue;
                }
                if ($row['sourceType'] !== ExternalPartLinkSourceType::TEMPLATE) {
                    continue;
                }

                $error = $this->validateTemplate((string) ($row['source'] ?? ''));
                if ($error !== null && $form->has((string) $idx) && $form->get((string) $idx)->has('source')) {
                    $form->get((string) $idx)->get('source')->addError(new FormError($error));
                }
            }
        });
    }

    /**
     * Returns a translated error message if $template is invalid, or null if it's fine.
     */
    private function validateTemplate(string $template): ?string
    {
        $unknownPlaceholder = ExternalPartLinkTemplateValidator::findUnknownPlaceholder($template);
        if ($unknownPlaceholder !== null) {
            return $this->translator->trans(
                'settings.external_part_links.rules.unknown_placeholder',
                ['%placeholder%' => $unknownPlaceholder],
                'validators'
            );
        }

        if (!ExternalPartLinkTemplateValidator::resolvesToAllowedUrlWithSampleValues($template)) {
            return $this->translator->trans('settings.external_part_links.rules.invalid_template', [], 'validators');
        }

        return null;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'entry_type' => ExternalPartLinkRuleType::class,
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
            'required' => false,
            'prototype' => true,
            'empty_data' => [],
            'entry_options' => ['label' => false],
        ]);
    }

    public function getParent(): ?string
    {
        return CollectionType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'external_part_link_rules_collection';
    }
}
