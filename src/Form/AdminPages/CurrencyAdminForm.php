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

namespace App\Form\AdminPages;

use App\Entity\Base\AbstractNamedDBElement;
use App\Form\Type\BigDecimalMoneyType;
use App\Services\LogSystem\EventCommentNeededHelper;
use Symfony\Component\Form\Extension\Core\Type\CurrencyType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Security;

class CurrencyAdminForm extends BaseEntityAdminForm
{
    public function __construct(\Symfony\Bundle\SecurityBundle\Security $security, EventCommentNeededHelper $eventCommentNeededHelper, private readonly string $default_currency)
    {
        parent::__construct($security, $eventCommentNeededHelper);
    }

    protected function additionalFormElements(FormBuilderInterface $builder, array $options, AbstractNamedDBElement $entity): void
    {
        $is_new = null === $entity->getID();

        $builder->add('iso_code', CurrencyType::class, [
            'required' => false,
            'label' => 'currency.edit.iso_code',
            'preferred_choices' => ['EUR', 'USD', 'GBP', 'JPY', 'CNY'],
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
