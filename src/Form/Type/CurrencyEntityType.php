<?php
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

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\PriceInformations\Currency;
use App\Form\Type\Helper\StructuralEntityChoiceHelper;
use App\Services\Trees\NodesListBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Intl\Currencies;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * An entity to select a currency shortly
 */
class CurrencyEntityType extends StructuralEntityType
{
    protected ?string $base_currency;

    public function __construct(EntityManagerInterface $em, NodesListBuilder $builder, TranslatorInterface $translator, StructuralEntityChoiceHelper $choiceHelper, ?string $base_currency)
    {
        parent::__construct($em, $builder, $translator, $choiceHelper);
        $this->base_currency = $base_currency;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        //Important to call the parent resolver!
        parent::configureOptions($resolver);

        $resolver->setDefault('class', Currency::class);
        $resolver->setDefault('disable_not_selectable', true);
        $resolver->setDefault('choice_translation_domain', false);

        // This options allows you to override the currency shown for the null value
        $resolver->setDefault('base_currency', null);

        $resolver->setDefault('choice_attr', function (Options $options) {
            return function ($choice) use ($options) {
                return $this->choice_helper->generateChoiceAttrCurrency($choice, $options);
            };
        });

        $resolver->setDefault('empty_message', function (Options $options) {
            //By default, we use the global base currency:
            $iso_code = $this->base_currency;

            if ($options['base_currency']) { //Allow to override it
                $iso_code = $options['base_currency'];
            }

            return Currencies::getSymbol($iso_code);
        });

        $resolver->setDefault('used_to_select_parent', false);

        //If short is set to true, then the name of the entity will only show in the dropdown list not in the selected value.
        $resolver->setDefault('short', false);
    }
}
