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
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

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
        $providers = $this->providerRegistry->getActiveProviders();

        $resolver->setDefault('input', 'object');
        $resolver->setAllowedTypes('input', 'string');
        //Either the form returns the provider objects or their keys
        $resolver->setAllowedValues('input', ['object', 'string']);
        $resolver->setDefault('multiple', true);

        $resolver->setDefault('choices', function (Options $options) use ($providers) {
            if ('object' === $options['input']) {
                return $this->providerRegistry->getActiveProviders();
            }

            $tmp = [];
            foreach ($providers as $provider) {
                $name = $provider->getProviderInfo()['name'];
                $tmp[$name] = $provider->getProviderKey();
            }

            return $tmp;
        });

        //The choice_label and choice_value only needs to be set if we want the objects
        $resolver->setDefault('choice_label', function (Options $options){
            if ('object' === $options['input']) {
                return ChoiceList::label($this, static fn (?InfoProviderInterface $choice) => $choice?->getProviderInfo()['name']);
            }

            return null;
        });
        $resolver->setDefault('choice_value', function (Options $options) {
            if ('object' === $options['input']) {
                return ChoiceList::value($this, static fn(?InfoProviderInterface $choice) => $choice?->getProviderKey());
            }

            return null;
        });
    }

}
