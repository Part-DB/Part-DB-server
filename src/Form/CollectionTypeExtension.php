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
use ReflectionException;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormConfigBuilder;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Perform a reindexing on CollectionType elements, by assigning the database id as index.
 * This prevents issues when the collection that is edited uses a OrderBy annotation and therefore the direction of the
 * elements can change during requests.
 * Must me enabled by setting reindex_enable to true in Type options.
 */
class CollectionTypeExtension extends AbstractTypeExtension
{
    protected PropertyAccessorInterface $propertyAccess;

    public function __construct(PropertyAccessorInterface $propertyAccess)
    {
        $this->propertyAccess = $propertyAccess;
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

        $resolver->setAllowedTypes('reindex_enable', 'bool');
        $resolver->setAllowedTypes('reindex_prefix', 'string');
        $resolver->setAllowedTypes('reindex_path', 'string');
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
    }

    /**
     * Set the option of the form.
     * This a bit hacky cause we access private properties....
     *
     */
    public function setOption(FormBuilder $builder, string $option, $value): void
    {
        //We have to use FormConfigBuilder::class here, because options is private and not available in sub classes
        $reflection = new ReflectionClass(FormConfigBuilder::class);
        $property = $reflection->getProperty('options');
        $property->setAccessible(true);
        $tmp = $property->getValue($builder);
        $tmp[$option] = $value;
        $property->setValue($builder, $tmp);
        $property->setAccessible(false);
    }
}
