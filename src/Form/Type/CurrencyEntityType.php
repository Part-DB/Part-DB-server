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

namespace App\Form\Type;

use App\Entity\Base\StructuralDBElement;
use App\Entity\PriceInformations\Currency;
use App\Services\Trees\NodesListBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Intl\Currencies;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CurrencyEntityType extends StructuralEntityType
{
    protected $base_currency;

    public function __construct(EntityManagerInterface $em, NodesListBuilder $builder, $base_currency)
    {
        parent::__construct($em, $builder);
        $this->base_currency = $base_currency;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        //Important to call the parent resolver!
        parent::configureOptions($resolver);

        $resolver->setDefault('class', Currency::class);
        $resolver->setDefault('disable_not_selectable', true);

        // This options allows you to override the currency shown for the null value
        $resolver->setDefault('base_currency', null);

        $resolver->setDefault('empty_message', function (Options $options) {
            //By default we use the global base currency:
            $iso_code = $this->base_currency;

            if ($options['base_currency']) { //Allow to override it
                $iso_code = $options['base_currency'];
            }

            return Currencies::getSymbol($iso_code);
        });
    }

    public function generateChoiceLabels(StructuralDBElement $choice, $key, $value): string
    {
        //Similar to StructuralEntityType, but we use the currency symbol instead if available

        /** @var StructuralDBElement|null $parent */
        $parent = $this->options['subentities_of'];

        /*** @var Currency $choice */
        $level = $choice->getLevel();
        //If our base entity is not the root level, we need to change the level, to get zero position
        if (null !== $this->options['subentities_of']) {
            $level -= $parent->getLevel() - 1;
        }

        $tmp = str_repeat('&nbsp;&nbsp;&nbsp;', $choice->getLevel()); //Use 3 spaces for intendation
        if (empty($choice->getIsoCode())) {
            $tmp .= htmlspecialchars($choice->getName());
        } else {
            $tmp .= Currencies::getSymbol($choice->getIsoCode());
        }

        return $tmp;
    }

    protected function generateChoiceAttr(StructuralDBElement $choice, $key, $value): array
    {
        /** @var Currency $choice */
        $tmp = [];

        if (!empty($choice->getIsoCode())) {
            //Show the name of the currency
            $tmp += ['data-subtext' => $choice->getName()];
        }

        //Disable attribute if the choice is marked as not selectable
        if ($this->options['disable_not_selectable'] && $choice->isNotSelectable()) {
            $tmp += ['disabled' => 'disabled'];
        }

        return $tmp;
    }
}
