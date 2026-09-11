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

namespace App\Form\Settings;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for editing the custom KiCad footprints and symbols lists.
 */
final class KicadListEditorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('useCustomList', CheckboxType::class, [
                'label' => 'settings.misc.kicad_eda.use_custom_list',
                'help' => 'settings.misc.kicad_eda.use_custom_list.help',
                'required' => false,
            ])
            ->add('customFootprints', TextareaType::class, [
                'label' => 'settings.misc.kicad_eda.editor.custom_footprints',
                'help' => 'settings.misc.kicad_eda.editor.footprints.help',
                'attr' => [
                    'rows' => 16,
                    'spellcheck' => 'false',
                    'class' => 'font-monospace',
                ],
            ])
            ->add('defaultFootprints', TextareaType::class, [
                'label' => 'settings.misc.kicad_eda.editor.default_footprints',
                'help' => 'settings.misc.kicad_eda.editor.default_files_help',
                'disabled' => true,
                'mapped' => false,
                'data' => $options['default_footprints'],
                'attr' => [
                    'rows' => 16,
                    'spellcheck' => 'false',
                    'class' => 'font-monospace',
                    'readonly' => 'readonly',
                ],
            ])
            ->add('customSymbols', TextareaType::class, [
                'label' => 'settings.misc.kicad_eda.editor.custom_symbols',
                'help' => 'settings.misc.kicad_eda.editor.symbols.help',
                'attr' => [
                    'rows' => 16,
                    'spellcheck' => 'false',
                    'class' => 'font-monospace',
                ],
            ])
            ->add('defaultSymbols', TextareaType::class, [
                'label' => 'settings.misc.kicad_eda.editor.default_symbols',
                'help' => 'settings.misc.kicad_eda.editor.default_files_help',
                'disabled' => true,
                'mapped' => false,
                'data' => $options['default_symbols'],
                'attr' => [
                    'rows' => 16,
                    'spellcheck' => 'false',
                    'class' => 'font-monospace',
                    'readonly' => 'readonly',
                ],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'save',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'default_footprints' => '',
            'default_symbols' => '',
        ]);
        $resolver->setAllowedTypes('default_footprints', 'string');
        $resolver->setAllowedTypes('default_symbols', 'string');
    }
}
