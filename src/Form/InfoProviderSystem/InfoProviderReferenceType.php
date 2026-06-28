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

use App\Entity\Parts\InfoProviderReference;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InfoProviderReferenceType extends AbstractType implements DataMapperInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->setDataMapper($this)
            ->add('provider_key', ProviderSelectType::class, [
                'label' =>  'info_providers.provider_key',
                'input' => 'string',
                'multiple' => false,
                'required' => false,
                'only_active' => false,
            ])
            ->add('provider_id', TextType::class, [
                'label' =>  'info_providers.provider_id',
                'required' => false,
            ])
            ->add('provider_url', UrlType::class, [
                'label' =>  'info_providers.provider_url',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InfoProviderReference::class,
        ]);
    }


    public function mapDataToForms(mixed $viewData, \Traversable $forms): void
    {
        if ($viewData === null) {
            return;
        }

        if (!$viewData instanceof InfoProviderReference) {
            return;
        }

        /** @var FormInterface[] $forms */
        $forms = iterator_to_array($forms);

        $forms['provider_key']->setData($viewData->getProviderKey());
        $forms['provider_id']->setData($viewData->getProviderId());
        $forms['provider_url']->setData($viewData->getProviderUrl());
    }

    public function mapFormsToData(\Traversable $forms, mixed &$viewData): void
    {
        /** @var FormInterface[] $forms */
        $forms = iterator_to_array($forms);

        $providerKey = $forms['provider_key']->getData();
        $providerId = $forms['provider_id']->getData();
        $providerUrl = $forms['provider_url']->getData();

        if ($viewData === null) {
            $viewData = InfoProviderReference::noProvider();
        }

        if (!$viewData instanceof InfoProviderReference) {
            return;
        }

        $oldDate = $viewData->getLastUpdated();

        //If all fields are empty, we set the view data to a new instance without provider information
        if ($providerKey === null && $providerId === null && $providerUrl === null) {
            $viewData = InfoProviderReference::noProvider();
            return;
        }

        $viewData = InfoProviderReference::create($providerKey, $providerId, $providerUrl, $oldDate);

    }
}
