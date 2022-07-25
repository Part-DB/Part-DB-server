<?php

declare(strict_types=1);

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
use App\Form\Type\RichTextEditorType;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;

class LabelOptionsType extends AbstractType
{
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('width', NumberType::class, [
            'label' => 'label_options.page_size.label',
            'html5' => true,
            'attr' => [
                'placeholder' => 'label_options.width.placeholder',
                'min' => 0,
                'step' => 'any',
            ],
        ]);
        $builder->add('height', NumberType::class, [
            'label' => false,
            'html5' => true,
            'attr' => [
                'placeholder' => 'label_options.height.placeholder',
                'min' => 0,
                'step' => 'any',
            ],
        ]);

        $builder->add('supported_element', ChoiceType::class, [
            'label' => 'label_options.supported_elements.label',
            'choices' => [
                'part.label' => 'part',
                'part_lot.label' => 'part_lot',
                'storelocation.label' => 'storelocation',
            ],
        ]);

        $builder->add('barcode_type', ChoiceType::class, [
            'label' => 'label_options.barcode_type.label',
            'empty_data' => 'none',
            'choices' => [
                'label_options.barcode_type.none' => 'none',
                'label_options.barcode_type.qr' => 'qr',
                'label_options.barcode_type.code128' => 'code128',
                'label_options.barcode_type.code39' => 'code39',
                'label_options.barcode_type.code93' => 'code93',
                'label_options.barcode_type.datamatrix' => 'datamatrix',
            ],
            'group_by' => static function ($choice, $key, $value) {
                if (in_array($choice, ['qr', 'datamatrix'], true)) {
                    return 'label_options.barcode_type.2D';
                }
                if (in_array($choice, ['code39', 'code93', 'code128'], true)) {
                    return 'label_options.barcode_type.1D';
                }

                return null;
            },
            'attr' => [
                'data-controller' => 'elements--selectpicker',
                'title' => 'selectpicker.nothing_selected',
                'data-live-search' => true,
            ],
        ]);

        $builder->add('lines', RichTextEditorType::class, [
            'label' => 'label_profile.lines.label',
            'empty_data' => '',
            'output_format' => 'html',
            'attr' => [
                'rows' => 4,
            ],
        ]);

        $builder->add('additional_css', TextareaType::class, [
            'label' => 'label_options.additional_css.label',
            'empty_data' => '',
            'attr' => [
                'rows' => 4,
            ],
            'required' => false,
        ]);

        $builder->add('lines_mode', ChoiceType::class, [
            'label' => 'label_options.lines_mode.label',
            'choices' => [
                'label_options.lines_mode.html' => 'html',
                'label.options.lines_mode.twig' => 'twig',
            ],
            'help' => 'label_options.lines_mode.help',
            'help_html' => true,
            'expanded' => true,
            'attr' => [
                'class' => 'pt-2',
            ],
            'label_attr' => [
                'class' => 'radio-custom radio-inline',
            ],
            'disabled' => !$this->security->isGranted('@labels.use_twig'),
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('data_class', LabelOptions::class);
    }
}
