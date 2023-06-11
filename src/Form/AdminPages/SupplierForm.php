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
use App\Entity\PriceInformations\Currency;
use App\Form\Type\BigDecimalMoneyType;
use App\Form\Type\StructuralEntityType;
use App\Services\LogSystem\EventCommentNeededHelper;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Security;

class SupplierForm extends CompanyForm
{
    public function __construct(\Symfony\Bundle\SecurityBundle\Security $security, EventCommentNeededHelper $eventCommentNeededHelper, protected string $default_currency)
    {
        parent::__construct($security, $eventCommentNeededHelper);
    }

    protected function additionalFormElements(FormBuilderInterface $builder, array $options, AbstractNamedDBElement $entity): void
    {
        $is_new = null === $entity->getID();

        parent::additionalFormElements($builder, $options, $entity);

        $builder->add('default_currency', StructuralEntityType::class, [
            'class' => Currency::class,
            'required' => false,
            'label' => 'supplier.edit.default_currency',
            'disable_not_selectable' => true,
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity),
        ]);

        $builder->add('shipping_costs', BigDecimalMoneyType::class, [
            'required' => false,
            'currency' => $this->default_currency,
            'scale' => 3,
            'label' => 'supplier.shipping_costs.label',
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity),
        ]);
    }
}
