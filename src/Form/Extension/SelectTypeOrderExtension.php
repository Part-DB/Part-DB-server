<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SelectTypeOrderExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [
            ChoiceType::class,
            EnumType::class
        ];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('ordered', false);
        $resolver->setDefault('by_reference', function (Options $options) {
            //Disable by_reference if the field is ordered (otherwise the order will be lost)
            return !$options['ordered'];
        });
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        //Pass the data in ordered form to the frontend controller, so it can make the items appear in the correct order.
        if ($options['ordered']) {
            $view->vars['attr']['data-ordered-value'] = json_encode($form->getViewData(), JSON_THROW_ON_ERROR);
        }
    }
}
