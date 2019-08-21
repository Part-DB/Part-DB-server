<?php
/**
 *
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/
 *
 * part-db version 0.2+
 * Copyright (C) 2009 K. Jacobs and others (see authors.php)
 * http://code.google.com/p/part-db/
 *
 * Part-DB Version 0.4+
 * Copyright (C) 2016 - 2019 Jan Böhmer
 * https://github.com/jbtronics
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


use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\Storelocation;
use App\Form\Type\StructuralEntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Exception;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use function GuzzleHttp\Promise\queue;

class PartLotType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('description', TextType::class, ['label' => 'part_lot.edit.description',
            'required' => false, 'empty_data' => "", 'attr' => ['class' => 'form-control-sm']]);

        $builder->add('storage_location', StructuralEntityType::class, ['class' => Storelocation::class,
            'label' => 'part_lot.edit.location',
            'required' => false,
            'disable_not_selectable' => true,
            'attr' => ['class' => 'selectpicker form-control-sm', 'data-live-search' => true]]);

        $builder->add('amount',NumberType::class, [ 'html5' => true,
            'label' => 'part_lot.edit.amount',
            'attr' => ['class' => 'form-control-sm', 'min' => 0, 'step' => 'any']
        ]);
        $builder->add('instock_unknown', CheckboxType::class, ['required' => false,
            'label' => 'part_lot.edit.instock_unknown',
            'attr' => ['class' => 'form-control-sm'],
            'label_attr'=> ['class' => 'checkbox-custom']]);
        $builder->add('needs_refill', CheckboxType::class, ['label_attr'=> ['class' => 'checkbox-custom'],
            'label' => 'part_lot.edit.needs_refill',
            'attr' => ['class' => 'form-control-sm'],
            'required' => false]);
        $builder->add('expirationDate', DateTimeType::class, [
            'label' => 'part_lot.edit.expiration_date',
            'attr' => [],
            'required' => false]);

        $builder->add('comment', TextType::class, ['label' => 'part_lot.edit.comment',
            'label' => 'part_lot.edit.comment',
            'attr' => ['class' => 'form-control-sm'],
            'required' => false, 'empty_data' => ""]);
    }


    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => PartLot::class,
        ]);
    }
}