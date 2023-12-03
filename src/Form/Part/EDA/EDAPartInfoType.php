<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Form\Part\EDA;

use App\Entity\EDA\EDAPartInfo;
use App\Form\Type\TriStateCheckboxType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

class EDAPartInfoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('reference_prefix', TextType::class, [
                    'label' => 'eda_info.reference_prefix',
                    'attr' => [
                        'placeholder' => t('eda_info.reference_prefix.placeholder'),
                    ]
                ]
            )
            ->add('value', TextType::class, [
                'label' => 'eda_info.value',
                'attr' => [
                    'placeholder' => t('eda_info.value.placeholder'),
                ]
            ])
            ->add('visibility', TriStateCheckboxType::class, [
                'help' => 'eda_info.visibility.help',
                'label' => 'eda_info.visibility',
            ])
            ->add('exclude_from_bom', TriStateCheckboxType::class, [
                'label' => 'eda_info.exclude_from_bom',
                'label_attr' => [
                    'class' => 'checkbox-inline'
                ]
            ])
            ->add('exclude_from_board', TriStateCheckboxType::class, [
                'label' => 'eda_info.exclude_from_board',
                'label_attr' => [
                    'class' => 'checkbox-inline'
                ]
            ])
            ->add('exclude_from_sim', TriStateCheckboxType::class, [
                'label' => 'eda_info.exclude_from_sim',
                'label_attr' => [
                    'class' => 'checkbox-inline'
                ]
            ])
            ->add('kicad_symbol', KicadFieldAutocompleteType::class, [
                'label' => 'eda_info.kicad_symbol',
                'type'  => KicadFieldAutocompleteType::TYPE_SYMBOL,
                'attr' => [
                    'placeholder' => t('eda_info.kicad_symbol.placeholder'),
                ]
            ])
            ->add('kicad_footprint', KicadFieldAutocompleteType::class, [
                'label' => 'eda_info.kicad_footprint',
                'type'  => KicadFieldAutocompleteType::TYPE_FOOTPRINT,
                'attr' => [
                    'placeholder' => t('eda_info.kicad_footprint.placeholder'),
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EDAPartInfo::class,
        ]);
    }
}