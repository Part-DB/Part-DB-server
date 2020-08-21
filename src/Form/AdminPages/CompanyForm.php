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
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;

class CompanyForm extends BaseEntityAdminForm
{
    protected function additionalFormElements(FormBuilderInterface $builder, array $options, AbstractNamedDBElement $entity): void
    {
        $is_new = null === $entity->getID();

        $builder->add('address', TextareaType::class, [
            'label' => 'company.edit.address',
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity),
            'attr' => [
                'placeholder' => 'company.edit.address.placeholder',
            ],
            'required' => false,
            'empty_data' => '',
        ]);

        $builder->add('phone_number', TelType::class, [
            'label' => 'company.edit.phone_number',
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity),
            'attr' => [
                'placeholder' => 'company.edit.phone_number.placeholder',
            ],
            'required' => false,
            'empty_data' => '',
        ]);

        $builder->add('fax_number', TelType::class, [
            'label' => 'company.edit.fax_number',
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity),
            'attr' => [
                'placeholder' => 'company.fax_number.placeholder',
            ],
            'required' => false,
            'empty_data' => '',
        ]);

        $builder->add('email_address', EmailType::class, [
            'label' => 'company.edit.email',
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity),
            'attr' => [
                'placeholder' => 'company.edit.email.placeholder',
            ],
            'required' => false,
            'empty_data' => '',
        ]);

        $builder->add('website', UrlType::class, [
            'label' => 'company.edit.website',
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity),
            'attr' => [
                'placeholder' => 'company.edit.website.placeholder',
            ],
            'required' => false,
            'empty_data' => '',
        ]);

        $builder->add('auto_product_url', UrlType::class, [
            'label' => 'company.edit.auto_product_url',
            'help' => 'company.edit.auto_product_url.help',
            'disabled' => !$this->security->isGranted($is_new ? 'create' : 'edit', $entity),
            'attr' => [
                'placeholder' => 'company.edit.auto_product_url.placeholder',
            ],
            'required' => false,
            'empty_data' => '',
        ]);
    }
}
