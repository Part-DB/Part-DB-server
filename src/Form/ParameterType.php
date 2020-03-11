<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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

use App\Entity\Parameters\AbstractParameter;
use App\Entity\Parameters\Parameter;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ParameterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('name', TextType::class,[
            'empty_data' => '',
        ]);
        $builder->add('symbol', TextType::class, [
            'required' => false,
            'empty_data' => '',
        ]);
        $builder->add('value_text', TextType::class, [
            'required' => false,
            'empty_data' => '',
        ]);

        $builder->add('value_max', NumberType::class, [
            'required' => false,
        ]);
        $builder->add('value_min', NumberType::class, [
            'required' => false,
        ]);
        $builder->add('value_typical', NumberType::class, [
            'required' => false
        ]);
        $builder->add('unit', TextType::class, [
            'required' => false,
            'empty_data' => '',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
                            'data_class' => AbstractParameter::class
                               ]);
    }
}