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

namespace App\Form\LabelSystem;

use App\Services\LabelSystem\BarcodeScanner\BarcodeSourceType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ScanDialogType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('input', TextType::class, [
            'label' => 'scan_dialog.input',
            //Do not trim the input, otherwise this damages Format06 barcodes which end with non-printable characters
            'trim' => false,
            'attr' => [
                'autofocus' => true,
                'id' => 'scan_dialog_input',
            ],
        ]);

        $builder->add('mode', EnumType::class, [
            'label' => 'scan_dialog.mode',
            'expanded' => true,
            'class' => BarcodeSourceType::class,
            'required' => false,
            'placeholder' => 'scan_dialog.mode.auto',
            'choice_label' => fn (?BarcodeSourceType $enum) => match($enum) {
                null => 'scan_dialog.mode.auto',
                BarcodeSourceType::INTERNAL => 'scan_dialog.mode.internal',
                BarcodeSourceType::IPN => 'scan_dialog.mode.ipn',
                BarcodeSourceType::USER_DEFINED => 'scan_dialog.mode.user',
                BarcodeSourceType::EIGP114 => 'scan_dialog.mode.eigp'
            },
        ]);

        $builder->add('info_mode', CheckboxType::class, [
            'label' => 'scan_dialog.info_mode',
            'required' => false,
        ]);

        $builder->add('submit', SubmitType::class, [
            'label' => 'scan_dialog.submit',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('mapped', false);
    }
}
