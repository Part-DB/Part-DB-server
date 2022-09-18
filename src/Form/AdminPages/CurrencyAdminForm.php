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

use App\Entity\Base\AbstractNamedDBElement;
use App\Form\Type\BigDecimalMoneyType;
use Symfony\Component\Form\Extension\Core\Type\CurrencyType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Security;

class CurrencyAdminForm extends BaseEntityAdminForm
{
    private string $default_currency;

    public function __construct(Security $security, string $default_currency)
    {
        parent::__construct($security);
        $this->default_currency = $default_currency;
    }

    protected function additionalFormElements(FormBuilderInterface $builder, array $options, AbstractNamedDBElement $entity): void
    {
        $is_new = null === $entity->getID();

        $builder->add('iso_code', CurrencyType::class, [
            'required' => false,
            'label' => 'currency.edit.iso_code',
            'preferred_choices' => ['EUR', 'USD', 'GBP', 'JPY', 'CNY'],
            'attr' => [
                'data-controller' => 'elements--selectpicker',
                'title' => 'selectpicker.nothing_selected',
                'data-live-search' => true,
            ],
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity),
        ]);

        $builder->add('exchange_rate', BigDecimalMoneyType::class, [
            'required' => false,
            'label' => 'currency.edit.exchange_rate',
            'currency' => $this->default_currency,
            'scale' => 6,
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity),
        ]);

        if (!$is_new) {
            $builder->add(
                'update_exchange_rate',
                SubmitType::class,
                [
                    'label' => 'currency.edit.update_rate',
                    'disabled' => !$this->security->isGranted('edit', $entity),
                ]
            );
        }
    }
}
