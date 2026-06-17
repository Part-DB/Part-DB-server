<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Form\OrderSystem;

use App\Entity\OrderSystem\OrderItem;
use App\Entity\Parts\Supplier;
use App\Form\Type\PartSelectType;
use App\Form\Type\StructuralEntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrderItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('part', PartSelectType::class, [
                'required' => false,
                'label' => 'order.item.part',
            ])
            ->add('name', TextType::class, [
                'label' => 'order.item.name',
                'required' => true,
            ])
            ->add('quantity', NumberType::class, [
                'label' => 'order.item.quantity',
                'html5' => true,
                'attr' => ['min' => 0, 'step' => 'any'],
            ])
            ->add('supplier', StructuralEntityType::class, [
                'class' => Supplier::class,
                'required' => false,
                'disable_not_selectable' => true,
                'label' => 'order.item.supplier',
            ])
            ->add('supplierPartNr', TextType::class, [
                'required' => false,
                'label' => 'order.item.supplier_part_nr',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OrderItem::class,
        ]);
    }
}
