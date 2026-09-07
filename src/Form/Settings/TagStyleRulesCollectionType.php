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

use App\Services\Tools\TagStyleColor;
use App\Services\Tools\TagStyleMatchType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Ordered list of tag style rule rows.
 * Model data: array<int, array{match_type: string, pattern: string, color: string}> (as stored in settings)
 * View data: same shape, but with match_type/color as enum instances (as expected by the row's EnumType fields)
 */
class TagStyleRulesCollectionType extends AbstractType
{
    private function toViewRows(array $modelValue): array
    {
        $out = [];
        foreach ($modelValue as $row) {
            if (!is_array($row)) {
                continue;
            }

            $matchType = TagStyleMatchType::tryFrom((string) ($row['match_type'] ?? ''));
            $color = TagStyleColor::tryFrom((string) ($row['color'] ?? ''));

            $out[] = [
                'matchType' => $matchType ?? TagStyleMatchType::EXACT,
                'pattern' => (string) ($row['pattern'] ?? ''),
                'color' => $color ?? TagStyleColor::DEFAULT,
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

            $matchType = $row['matchType'] ?? null;
            $color = $row['color'] ?? null;
            $pattern = $row['pattern'] ?? null;

            if (!$matchType instanceof TagStyleMatchType || !$color instanceof TagStyleColor
                || !is_string($pattern) || $pattern === '') {
                continue;
            }

            $out[] = [
                'match_type' => $matchType->value,
                'pattern' => $pattern,
                'color' => $color->value,
            ];
        }

        return $out;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        //The model transformer's transform() already converts the model rows to view rows whenever data is set on
        //this form (including the very first time), so a PRE_SET_DATA listener doing the same conversion would run
        //it a second time on its own output - failing as soon as a row exists, since match_type/color are already
        //enum instances by then instead of the strings toViewRows() expects.
        $builder->addModelTransformer(new CallbackTransformer(
            fn (array $modelValue) => $this->toViewRows($modelValue),
            fn (array $viewValue) => $this->toModelRows($viewValue),
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'entry_type' => TagStyleRuleType::class,
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
        return 'tag_style_rules_collection';
    }
}
