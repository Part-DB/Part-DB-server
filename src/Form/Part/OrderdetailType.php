<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony)
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
 *
 */

namespace App\Form\Part;

use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Supplier;
use App\Entity\PriceInformations\Orderdetail;
use App\Entity\PriceInformations\Pricedetail;
use App\Form\Type\StructuralEntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;
use function foo\func;

class OrderdetailType extends AbstractType
{
    protected $trans;
    protected $security;

    public function __construct(TranslatorInterface $trans, Security $security)
    {
        $this->trans = $trans;
        $this->security = $security;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /** @var Orderdetail $orderdetail */
        $orderdetail = $builder->getData();

        $builder->add('supplierpartnr', TextType::class, [
            'label' => $this->trans->trans('orderdetails.edit.supplierpartnr'),
            'attr' => ['placeholder' =>  $this->trans->trans('orderdetails.edit.supplierpartnr.placeholder')],
            'required' => false,
            'empty_data' => ""
        ]);

        $builder->add('supplier', StructuralEntityType::class, [
            'class' => Supplier::class, 'disable_not_selectable' => true,
            'label' =>  $this->trans->trans('orderdetails.edit.supplier')
        ]);

        $builder->add('supplier_product_url', UrlType::class, [
            'required' => false,
            'empty_data' => "",
            'label' =>  $this->trans->trans('orderdetails.edit.url')
        ]);

        $builder->add('obsolete', CheckboxType::class, [
            'required' => false,
            'label_attr' => ['class' => 'checkbox-custom'],
            'label' =>  $this->trans->trans('orderdetails.edit.obsolete')
        ]);


        //Add pricedetails after we know the data, so we can set the default currency
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options) {
            /** @var Orderdetail $orderdetail */
            $orderdetail = $event->getData();

            $dummy_pricedetail = new Pricedetail();
            if ($orderdetail !== null && $orderdetail->getSupplier() !== null) {
                $dummy_pricedetail->setCurrency($orderdetail->getSupplier()->getDefaultCurrency());
            }

            //Attachment section
            $event->getForm()->add('pricedetails', CollectionType::class, [
                'entry_type' => PricedetailType::class,
                'allow_add' => $this->security->isGranted('@parts_prices.create'),
                'allow_delete' => $this->security->isGranted('@parts_prices.delete'),
                'label' => false,
                'prototype_data' => $dummy_pricedetail,
                'by_reference' => false,
                'entry_options' => [
                    'disabled' => !$this->security->isGranted('@parts_prices.edit'),
                    'measurement_unit' => $options['measurement_unit']
                ]
            ]);

        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Orderdetail::class,
            'error_bubbling' => false,
        ]);

        $resolver->setRequired('measurement_unit');
        $resolver->setAllowedTypes('measurement_unit', [MeasurementUnit::class, 'null']);
    }
}