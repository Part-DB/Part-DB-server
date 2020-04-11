<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace App\Form;


use App\Entity\LabelSystem\LabelOptions;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LabelOptionsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('width', NumberType::class, [
            'label' => 'label_options.page_size.label',
            'html5' => true,
            'attr' => [
                'placeholder' => 'label_options.width.placeholder',
                'min' => 0,
                'step' => 'any',
            ]
        ]);
        $builder->add('height', NumberType::class, [
            'label' => false,
            'html5' => true,
            'attr' => [
                'placeholder' => 'label_options.height.placeholder',
                'min' => 0,
                'step' => 'any',
            ]
        ]);

        $builder->add('barcode_type', ChoiceType::class, [
           'label' => 'label_options.barcode_type.label',
            'empty_data' => 'none',
            'choices' => [
                'label_options.barcode_type.none' => 'none',
                'label_options.barcode_type.qr' => 'qr',
                'label_options.barcode_type.code39' => 'code39',
            ]
        ]);

        $builder->add('lines', CKEditorType::class, [
            'label' => 'label_profile.lines.label',
            'attr' => [
                'rows' => 4,
            ],
            'config_name' => 'label_config',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefault('data_class', LabelOptions::class);
    }
}