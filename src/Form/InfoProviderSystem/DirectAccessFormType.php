<?php
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

declare(strict_types=1);


namespace App\Form\InfoProviderSystem;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Form to retrieve the details of a part directly from an info provider by its provider ID, without searching first.
 */
class DirectAccessFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('provider', ProviderSelectType::class, [
            'label' => 'info_providers.direct_access.provider',
            'multiple' => false,
            'input' => 'string',
        ]);

        $builder->add('provider_id', TextType::class, [
            'label' => 'info_providers.direct_access.provider_id',
            'help' => 'info_providers.direct_access.provider_id.help',
            'constraints' => [
                new NotBlank(),
            ],
        ]);

        $builder->add('no_cache', CheckboxType::class, [
            'label' => 'info_providers.no_cache_details',
            'required' => false,
        ]);

        $builder->add('submit', SubmitType::class, [
            'label' => 'info_providers.direct_access.submit',
        ]);
    }
}
