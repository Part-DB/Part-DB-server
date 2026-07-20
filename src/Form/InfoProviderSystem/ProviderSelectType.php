<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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
use App\Services\InfoProviderSystem\Providers\InfoProviderInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\ChoiceList;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\StaticMessage;
use Symfony\Component\Translation\TranslatableMessage;

class ProviderSelectType extends AbstractType
{
    public function __construct(private readonly ProviderRegistry $providerRegistry)
    {
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('input', 'object');
        $resolver->setAllowedTypes('input', 'string');
        //Either the form returns the provider objects or their keys
        $resolver->setAllowedValues('input', ['object', 'string']);
        $resolver->setDefault('multiple', true);

        //Only show active providers in the list, or also inactive ones
        $resolver->setDefault('only_active', true);
        $resolver->setAllowedTypes('only_active', 'bool');


        $resolver->setDefault('choices', function (Options $options) {
            $providers = $options['only_active'] ? $this->providerRegistry->getActiveProviders() : $this->providerRegistry->getProviders();

            if ('object' === $options['input']) {
                return $providers;
            }

            $tmp = [];
            foreach ($providers as $provider) {
                $name = $provider->getProviderInfo()['name'];
                $tmp[$name] = $provider->getProviderKey();
            }

            return $tmp;
        });

        //The choice_label and choice_value only needs to be set if we want the objects
        $resolver->setDefault('choice_label', function (Options $options) {
            if ('object' === $options['input']) {
                return ChoiceList::label($this, static fn(?InfoProviderInterface $choice
                ) => new StaticMessage($choice?->getProviderInfo()['name']));
            }

            return static fn($choice, $key, $value) => new StaticMessage($key);
        });
        $resolver->setDefault('choice_value', function (Options $options) {
            if ('object' === $options['input']) {
                return ChoiceList::value($this,
                    static fn(?InfoProviderInterface $choice) => $choice?->getProviderKey());
            }

            return null;
        });
        $resolver->setDefault('group_by', function (Options $options) {
            //Do not show groups when only active providers are shown, because then all providers are active and the group would be useless
            if ($options['only_active']) {
                return null;
            }

            return function ($choice, $key, string $value) {
                if ($this->providerRegistry->getProviderByKey($value)->isActive()) {
                    return new TranslatableMessage('info_providers.providers_list.active');
                }
                return new TranslatableMessage('info_providers.providers_list.disabled');
            };
        });
    }

}
