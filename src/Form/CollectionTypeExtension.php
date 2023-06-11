<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Form;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use ReflectionClass;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Event\PreSubmitEvent;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormConfigBuilder;
use Symfony\Component\Form\FormConfigInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Perform a reindexing on CollectionType elements, by assigning the database id as index.
 * This prevents issues when the collection that is edited uses a OrderBy annotation and therefore the direction of the
 * elements can change during requests.
 * Must be enabled by setting reindex_enable to true in Type options.
 */
class CollectionTypeExtension extends AbstractTypeExtension
{
    public function __construct(protected PropertyAccessorInterface $propertyAccess)
    {
    }

    public static function getExtendedTypes(): iterable
    {
        return [CollectionType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        /*$resolver->setDefault('error_mapping', function (Options $options) {
                                    $options->
                                });*/

        $resolver->setDefaults([
            'reindex_enable' => false,
            'reindex_prefix' => 'db_',
            'reindex_path' => 'id',
        ]);

        //Set a unique prototype name, so that we can use nested collections
        $resolver->setDefaults([
            'prototype_name' => fn(Options $options): string => '__name_'.uniqid("", false) . '__',
        ]);

        $resolver->setAllowedTypes('reindex_enable', 'bool');
        $resolver->setAllowedTypes('reindex_prefix', 'string');
        $resolver->setAllowedTypes('reindex_path', 'string');
    }

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        parent::finishView($view, $form, $options);
        //Add prototype name to view, so that we can pass it to the stimulus controller
        $view->vars['prototype_name'] = $options['prototype_name'];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options): void {
            $data = $event->getData();
            $config = $event->getForm()->getConfig();
            //If enabled do a reindexing of the collection
            if ($options['reindex_enable'] && $data instanceof Collection) {
                $reindexed_data = new ArrayCollection();

                $error_mapping = [];

                foreach ($data->toArray() as $key => $item) {
                    $id = $this->propertyAccess->getValue($item, $options['reindex_path']);
                    //If element has an ID then use it. otherwise use default key
                    $index = null === $id ? $key : $options['reindex_prefix'].$id;
                    $error_mapping['['.$key.']'] = $index;
                    $reindexed_data->set($index, $item);
                }
                $event->setData($reindexed_data);

                //Add error mapping, so that validator error are mapped correctly to the new index fields
                if ($config instanceof FormBuilder && empty($config->getOption('error_mapping'))) {
                    $this->setOption($config, 'error_mapping', $error_mapping);
                }
            }
        }, 100); //We need to have a higher priority then the PRE_SET_DATA listener on CollectionType

        // This event listener fixes the error mapping for newly created elements of collection types
        // Without this method, the errors for newly created elements are shown on the parent element, as forms
        // can not map it to the correct element.
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (PreSubmitEvent $event) {
           $data = $event->getData();
           $form = $event->getForm();
           $config = $form->getConfig();

           if (!is_array($data) && !$data instanceof Collection) {
               return;
           }

           if ($data instanceof Collection) {
                $data = $data->toArray();
           }

           //The validator uses the number  of the element as index, so we have to map the errors to the correct index
           $error_mapping = [];
           $n = 0;
           foreach (array_keys($data) as $key) {
               $error_mapping['['.$n.']'] = $key;
               $n++;
           }
            $this->setOption($config, 'error_mapping', $error_mapping);
        });
    }

    /**
     * Set the option of the form.
     * This a bit hacky because we access private properties....
     *
     */
    public function setOption(FormConfigInterface $builder, string $option, $value): void
    {
        if (!$builder instanceof FormConfigBuilder) {
            throw new \RuntimeException('This method only works with FormConfigBuilder instances.');
        }

        //We have to use FormConfigBuilder::class here, because options is private and not available in subclasses
        $reflection = new ReflectionClass(FormConfigBuilder::class);
        $property = $reflection->getProperty('options');
        $property->setAccessible(true);
        $tmp = $property->getValue($builder);
        $tmp[$option] = $value;
        $property->setValue($builder, $tmp);
        $property->setAccessible(false);
    }
}
