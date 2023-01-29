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

use App\Entity\Attachments\AttachmentType;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\PriceInformations\Currency;
use App\Services\Attachments\AttachmentURLGenerator;
use App\Services\Trees\NodesListBuilder;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\Intl\Currencies;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * An entity to select a currency shortly
 */
class CurrencyEntityType extends StructuralEntityType
{
    protected ?string $base_currency;

    public function __construct(EntityManagerInterface $em, NodesListBuilder $builder, AttachmentURLGenerator $attachmentURLGenerator, ?string $base_currency)
    {
        parent::__construct($em, $builder, $attachmentURLGenerator);
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

        $resolver->setDefault('empty_message', function (Options $options) {
            //By default we use the global base currency:
            $iso_code = $this->base_currency;

            if ($options['base_currency']) { //Allow to override it
                $iso_code = $options['base_currency'];
            }

            return Currencies::getSymbol($iso_code);
        });

        $resolver->setDefault('used_to_select_parent', false);

        //If short is set to true, then the name of the entity will only shown in the dropdown list not in the selected value.
        $resolver->setDefault('short', false);
    }

    protected function generateChoiceAttr(AbstractStructuralDBElement $choice, $key, $value, $options): array
    {
        $tmp = parent::generateChoiceAttr($choice, $key, $value, $options);

        if(!empty($choice->getIsoCode())) {
            $symbol = Currencies::getSymbol($choice->getIsoCode());
        } else {
            $symbol = null;
        }

        if ($options['short']) {
            $tmp['data-short'] = $symbol;
        } else {
            $tmp['data-short'] = $choice->getName();
        }

        $tmp += [
            'data-symbol' => $symbol,
        ];

        return $tmp;
    }

    protected function getChoiceContent(AbstractStructuralDBElement $choice, $key, $value, $options): string
    {
        if(!$choice instanceof Currency) {
            throw new RuntimeException('$choice must be an instance of Currency!');
        }

        //Generate the level spacing
        /** @var AbstractStructuralDBElement|null $parent */
        $parent = $options['subentities_of'];
        /*** @var AbstractStructuralDBElement $choice */
        $level = $choice->getLevel();
        //If our base entity is not the root level, we need to change the level, to get zero position
        if (null !== $options['subentities_of']) {
            $level -= $parent->getLevel() - 1;
        }

        $tmp = str_repeat('<span class="picker-level"></span>', $level);

        //Show currency symbol or ISO code and the name of the currency
        if(!empty($choice->getIsoCode())) {
            $tmp .= Currencies::getSymbol($choice->getIsoCode());
            //Add currency name as badge
            $tmp .= sprintf('<span class="badge bg-primary ms-2 %s">%s</span>', $options['short'] ? 'picker-hs' : '' , htmlspecialchars($choice->getName()));
        } else {
            $tmp .= htmlspecialchars($choice->getName());
        }

        return $tmp;
    }

}
