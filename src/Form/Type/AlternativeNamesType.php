<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A tag-input field that maps a string[] model property to a tab-separated string in the view.
 * The tagsinput Stimulus controller renders it as a tag chip widget; tabs are decoded by the
 * controller's separator option so commas can appear freely inside individual tags.
 */
class AlternativeNamesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CallbackTransformer(
            static fn(array $names): string => implode("\t", $names),
            static fn(string $csv): array => array_values(
                array_filter(
                    array_map('trim', explode("\t", $csv)),
                    static fn(string $s): bool => $s !== ''
                )
            )
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'required'   => false,
            'label'      => 'entity.edit.alternative_names.label',
            'help'       => 'entity.edit.alternative_names.help',
            'empty_data' => '',
            'attr'       => [
                'class'                                        => 'tagsinput',
                'data-controller'                              => 'elements--tagsinput',
                'data-elements--tagsinput-separator-value'     => '\\t',
            ],
        ]);
    }

    public function getParent(): string
    {
        return TextType::class;
    }
}
