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
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A single tag style rule row: match type + pattern + color.
 */
class TagStyleRuleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('matchType', EnumType::class, [
                'class' => TagStyleMatchType::class,
                'label' => false,
                'required' => true,
                'choice_label' => fn (TagStyleMatchType $choice) => $choice->toTranslationKey(),
                'constraints' => [new Assert\NotNull()],
                'row_attr' => ['class' => 'mb-0'],
                //Keeps the live preview in the row (see the pages/tag_style_rule Stimulus controller) in sync
                'attr' => [
                    'class' => 'form-select-sm',
                    'data-pages--tag-style-rule-target' => 'matchType',
                    'data-action' => 'change->pages--tag-style-rule#update',
                ],
            ])
            ->add('pattern', TextType::class, [
                'label' => false,
                'required' => true,
                'empty_data' => '',
                'constraints' => [new Assert\NotBlank()],
                'row_attr' => ['class' => 'mb-0'],
                'attr' => [
                    'class' => 'form-control-sm',
                    'data-pages--tag-style-rule-target' => 'pattern',
                    'data-action' => 'input->pages--tag-style-rule#update',
                ],
            ])
            ->add('color', EnumType::class, [
                'class' => TagStyleColor::class,
                'label' => false,
                'required' => true,
                'choice_label' => fn (TagStyleColor $choice) => $choice->toTranslationKey(),
                'constraints' => [new Assert\NotNull()],
                'row_attr' => ['class' => 'mb-0'],
                'attr' => [
                    'class' => 'form-select-sm',
                    'data-pages--tag-style-rule-target' => 'color',
                    'data-action' => 'change->pages--tag-style-rule#update',
                ],
            ]);
    }
}
