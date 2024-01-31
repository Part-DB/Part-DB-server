<?php
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

declare(strict_types=1);

namespace App\Form\Part;

use Symfony\Bundle\SecurityBundle\Security;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\StorageLocation;
use App\Form\Type\SIUnitType;
use App\Form\Type\StructuralEntityType;
use App\Form\Type\UserSelectType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PartLotType extends AbstractType
{
    public function __construct(protected Security $security)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('description', TextType::class, [
            'label' => 'part_lot.edit.description',
            'required' => false,
            'empty_data' => '',
            'attr' => [
                'class' => 'form-control-sm',
            ],
        ]);

        $builder->add('storage_location', StructuralEntityType::class, [
            'class' => StorageLocation::class,
            'label' => 'part_lot.edit.location',
            'required' => false,
            'disable_not_selectable' => true,
            'allow_add' => $this->security->isGranted('@storelocations.create'),
        ]);

        $builder->add('amount', SIUnitType::class, [
            'measurement_unit' => $options['measurement_unit'],
            'required' => false,
            'label' => 'part_lot.edit.amount',
            'attr' => [
                'class' => 'form-control-sm',
            ],
        ]);

        $builder->add('instock_unknown', CheckboxType::class, [
            'required' => false,
            'label' => 'part_lot.edit.instock_unknown',
        ]);

        $builder->add('needs_refill', CheckboxType::class, [
            'label' => 'part_lot.edit.needs_refill',
            'required' => false,
        ]);

        $builder->add('expiration_date', DateType::class, [
            'label' => 'part_lot.edit.expiration_date',
            'attr' => [],
            'widget' => 'single_text',
            'model_timezone' => 'UTC',
            'required' => false,
        ]);

        $builder->add('comment', TextType::class, [
            'label' => 'part_lot.edit.comment',
            'attr' => [
                'class' => 'form-control-sm',
            ],
            'required' => false,
            'empty_data' => '',
        ]);

        $builder->add('owner', UserSelectType::class, [
            'label' => 'part_lot.owner',
            'required' => false,
            'help' => 'part_lot.owner.help',
        ]);

        $builder->add('vendor_barcode', TextType::class, [
            'label' => 'part_lot.edit.vendor_barcode',
            'help' => 'part_lot.edit.vendor_barcode.help',
            'required' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PartLot::class,
        ]);

        $resolver->setRequired('measurement_unit');
        $resolver->setAllowedTypes('measurement_unit', [MeasurementUnit::class, 'null']);
    }
}
