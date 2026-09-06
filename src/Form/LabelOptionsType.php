<?php
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

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

use App\Entity\LabelSystem\BarcodeType;
use App\Entity\LabelSystem\LabelProcessMode;
use App\Entity\LabelSystem\LabelSupportedElement;
use Symfony\Bundle\SecurityBundle\Security;
use App\Entity\LabelSystem\LabelOptions;
use App\Form\Type\RichTextEditorType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LabelOptionsType extends AbstractType
{
    public function __construct(private readonly Security $security)
    {
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

        $builder->add('supported_element', EnumType::class, [
            'label' => 'label_options.supported_elements.label',
            'class' => LabelSupportedElement::class,
            'choice_label' => fn(LabelSupportedElement $choice) => match($choice) {
                LabelSupportedElement::PART => 'part.label',
                LabelSupportedElement::PART_LOT => 'part_lot.label',
                LabelSupportedElement::STORELOCATION => 'storelocation.label',
            },
        ]);

        $builder->add('barcode_type', EnumType::class, [
            'label' => 'label_options.barcode_type.label',
            'empty_data' => 'none',
            'class' => BarcodeType::class,
            'choice_label' => fn(BarcodeType $choice) => match($choice) {
                BarcodeType::NONE => 'label_options.barcode_type.none',
                BarcodeType::QR => 'label_options.barcode_type.qr',
                BarcodeType::CODE128 => 'label_options.barcode_type.code128',
                BarcodeType::CODE39 => 'label_options.barcode_type.code39',
                BarcodeType::CODE93 => 'label_options.barcode_type.code93',
                BarcodeType::DATAMATRIX => 'label_options.barcode_type.datamatrix',
            },
            'group_by' => static function (BarcodeType $choice, $key, $value): ?string {
                if ($choice->is2D()) {
                    return 'label_options.barcode_type.2D';
                }
                if ($choice->is1D()) {
                    return 'label_options.barcode_type.1D';
                }

                return null;
            },
        ]);

        //The "lines" field is added via an event listener instead of directly, because whether the CKEditor
        //WYSIWYG editor should be initially attached depends on the entity's current process_mode: The editor
        //treats its content as HTML and therefore escapes characters like <, > and & (e.g. in
        //"filter(v => v.id > 1)"), which corrupts Twig expressions. So we only auto-attach it in HTML/placeholder
        //mode. The label_lines_mode Stimulus controller (data-action/data-controller below) takes care of
        //(de)activating the editor on the fly, when the user switches the mode without reloading the page.
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $data = $event->getData();
            $processMode = $data instanceof LabelOptions ? $data->getProcessMode() : LabelProcessMode::PLACEHOLDER;

            $event->getForm()->add('lines', RichTextEditorType::class, [
                'label' => 'label_profile.lines.label',
                'empty_data' => '',
                'mode' => 'html-label',
                'auto_init' => $processMode === LabelProcessMode::PLACEHOLDER,
                'attr' => [
                    'rows' => 4,
                    'data-ck-class' => 'ck-html-label',
                    'data-controller' => 'pages--label-lines-mode',
                    'data-action' => 'label-lines-mode:change@document->pages--label-lines-mode#toggle',
                ],
            ]);
        });

        $builder->add('additional_css', TextareaType::class, [
            'label' => 'label_options.additional_css.label',
            'empty_data' => '',
            'attr' => [
                'rows' => 4,
            ],
            'required' => false,
        ]);

        $builder->add('process_mode', EnumType::class, [
            'label' => 'label_options.lines_mode.label',
            'class' => LabelProcessMode::class,
            'choice_label' => fn(LabelProcessMode $choice) => match($choice) {
                LabelProcessMode::PLACEHOLDER => 'label_options.lines_mode.html',
                LabelProcessMode::TWIG => 'label.options.lines_mode.twig',
            },
            'help' => 'label_options.lines_mode.help',
            'help_html' => true,
            'expanded' => true,
            'attr' => [
                'class' => 'pt-2',
                'data-controller' => 'pages--label-lines-mode',
                'data-action' => 'change->pages--label-lines-mode#notify',
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
