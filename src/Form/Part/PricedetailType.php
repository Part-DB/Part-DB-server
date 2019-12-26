<?php
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

namespace App\Form\Part;

use App\Entity\Parts\MeasurementUnit;
use App\Entity\PriceInformations\Pricedetail;
use App\Form\Type\CurrencyEntityType;
use App\Form\Type\SIUnitType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PricedetailType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        //No labels needed, we define translation in templates
        $builder->add('min_discount_quantity', SIUnitType::class, [
            'label' => false,
            'measurement_unit' => $options['measurement_unit'],
            'attr' => ['class' => 'form-control-sm'],
        ]);
        $builder->add('price_related_quantity', SIUnitType::class, [
            'label' => false,
            'measurement_unit' => $options['measurement_unit'],
            'attr' => ['class' => 'form-control-sm'],
        ]);
        $builder->add('price', NumberType::class, [
            'label' => false,
            'scale' => 5,
            'html5' => true,
            'attr' => ['min' => 0, 'step' => 'any'],
        ]);
        $builder->add('currency', CurrencyEntityType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Pricedetail::class,
            'error_bubbling' => false,
        ]);

        $resolver->setRequired('measurement_unit');
        $resolver->setAllowedTypes('measurement_unit', [MeasurementUnit::class, 'null']);
    }
}
