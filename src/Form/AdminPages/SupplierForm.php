<?php

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Form\AdminPages;

use App\Entity\Base\NamedDBElement;
use App\Entity\PriceInformations\Currency;
use App\Form\Type\StructuralEntityType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;

class SupplierForm extends CompanyForm
{
    protected function additionalFormElements(FormBuilderInterface $builder, array $options, NamedDBElement $entity): void
    {
        $is_new = null === $entity->getID();

        parent::additionalFormElements($builder, $options, $entity);

        $builder->add('default_currency', StructuralEntityType::class, [
            'class' => Currency::class,
            'required' => false,
            'label' => 'supplier.edit.default_currency',
            'disable_not_selectable' => true,
            'disabled' => ! $this->security->isGranted($is_new ? 'create' : 'move', $entity), ]);

        $builder->add('shipping_costs', MoneyType::class, [
            'required' => false,
            'currency' => $this->params->get('default_currency'),
            'scale' => 3,
            'label' => 'supplier.shipping_costs.label',
            'disabled' => ! $this->security->isGranted($is_new ? 'create' : 'move', $entity),
        ]);
    }
}
