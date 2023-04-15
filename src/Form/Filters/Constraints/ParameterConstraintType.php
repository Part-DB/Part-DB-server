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

namespace App\Form\Filters\Constraints;

use App\DataTables\Filters\Constraints\Part\ParameterConstraint;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ParameterConstraintType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => true,
            'data_class' => ParameterConstraint::class,
            'empty_data' => new ParameterConstraint(),
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, [
            'required' => false,
        ]);

        $builder->add('unit', SearchType::class, [
            'required' => false,
        ]);

        $builder->add('symbol', SearchType::class, [
            'required' => false
        ]);

        $builder->add('value_text', TextConstraintType::class, [
            //'required' => false,
        ] );

        $builder->add('value', ParameterValueConstraintType::class, [
        ]);

        /*
         * I am not quite sure why this is needed, but somehow symfony tries to create a new instance of TextConstraint
         * instead of using the existing one for the prototype (or the one from empty data). This fails as the constructor of TextConstraint requires
         * arguments.
         * Ensure that the data is never null, but use an empty ParameterConstraint instead
         */
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $data = $event->getData();

            if ($data === null) {
                $event->setData(new ParameterConstraint());
            }
        });
    }
}