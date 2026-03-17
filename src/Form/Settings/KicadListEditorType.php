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
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

final class KicadListEditorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('footprints', TextareaType::class, [
                'label' => 'settings.misc.kicad_eda.editor.footprints',
                'help' => 'settings.misc.kicad_eda.editor.footprints.help',
                'attr' => [
                    'rows' => 16,
                    'spellcheck' => 'false',
                    'class' => 'font-monospace',
                ],
            ])
            ->add('symbols', TextareaType::class, [
                'label' => 'settings.misc.kicad_eda.editor.symbols',
                'help' => 'settings.misc.kicad_eda.editor.symbols.help',
                'attr' => [
                    'rows' => 16,
                    'spellcheck' => 'false',
                    'class' => 'font-monospace',
                ],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'save',
            ]);
    }
}
