<?php
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
use Symfony\Component\Form\Extension\Core\Type\CurrencyType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;

class CurrencyAdminForm extends BaseEntityAdminForm
{
    protected function additionalFormElements(FormBuilderInterface $builder, array $options, NamedDBElement $entity)
    {
        $is_new = null === $entity->getID();

        $builder->add('iso_code', CurrencyType::class, [
            'required' => false,
            'label' => $this->trans->trans('currency.edit.iso_code'),
            'preferred_choices' => ['EUR', 'USD', 'GBP', 'JPY', 'CNY'],
            'attr' => ['class' => 'selectpicker', 'data-live-search' => true],
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity), ]);

        $builder->add('exchange_rate', MoneyType::class, [
            'required' => false,
            'label' => $this->trans->trans('currency.edit.exchange_rate'),
            'currency' => $this->params->get('default_currency'),
            'scale' => 6,
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity), ]);
    }
}
