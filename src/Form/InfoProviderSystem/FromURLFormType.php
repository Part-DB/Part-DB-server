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

use App\Services\InfoProviderSystem\ProviderRegistry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;

class FromURLFormType extends AbstractType
{
    public function __construct(private readonly ProviderRegistry $providerRegistry)
    {

    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('url', UrlType::class, [
            'label' => 'info_providers.from_url.url.label',
            'required' => true,
        ]);


        $builder->add('method', ChoiceType::class, [
            'expanded' => true,
            'data' => 'generic_web', //Default value
            'label' => 'info_providers.from_url.method',
            'choices' => [
                'info_providers.from_url.method.generic_web' => 'generic_web',
                'info_providers.from_url.method.ai_web' => 'ai_web',
            ],
            'choice_attr' => function ($choice, $key, $value) {
                //Disable all providers that are not active
                $provider = $this->providerRegistry->getProviderByKey($value);
                if (!$provider->isActive()) {
                    return ['disabled' => 'disabled'];
                }

                return [];
            },

            //Render the choices as inline radio buttons
            'label_attr' => [
                'class' => 'radio-inline',
            ],
        ]);

        $builder->add('no_cache', CheckboxType::class, [
            'label' => 'info_providers.from_url.no_cache',
            'required' => false,
        ]);

        $builder->add('submit', SubmitType::class, [
            'label' => 'info_providers.search.submit',
        ]);
    }
}
