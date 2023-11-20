<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Services\EntityMergers\Mergers;

use App\Entity\Parts\Part;
use App\Entity\Parts\PartAssociation;
use App\Entity\Parts\PartLot;
use App\Entity\PriceInformations\Orderdetail;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
class PartMerger implements EntityMergerInterface
{

    use EntityMergerHelperTrait;

    public function supports(object $target, object $other, array $context = []): bool
    {
        return $target instanceof Part && $other instanceof Part;
    }

    public function merge(object $target, object $other, array $context = []): Part
    {
        if (!$target instanceof Part || !$other instanceof Part) {
            throw new \InvalidArgumentException('The target and the other entity must be instances of Part');
        }

        //Merge basic infos
        $this->useOtherValueIfNotNull($target, $other, 'manufacturer');
        $this->useOtherValueIfNotNull($target, $other, 'footprint');
        $this->useOtherValueIfNotNull($target, $other, 'category');
        $this->useOtherValueIfNotNull($target, $other, 'partUnit');

        //We assume that the higher value is the correct one for minimum instock
        $this->useLargerValue($target, $other, 'minamount');

        //We assume that a part needs review and is a favorite if one of the parts is
        $this->useTrueValue($target, $other, 'needs_review');
        $this->useTrueValue($target, $other, 'favorite');

        //Merge the tags using the tag merger
        $this->mergeTags($target, $other, 'tags');

        return $target;
    }

    private function mergeCollectionFields(Part $target, Part $other, array $context): void
    {
        /********************************************************************************
         * Merge collections
         ********************************************************************************/

        //Lots from different parts are never considered equal, so we just merge them together
        $this->mergeCollections($target, $other, 'partLots');
        $this->mergeAttachments($target, $other);
        $this->mergeParameters($target, $other);

        $this->mergeCollections($target, $other, 'associated_parts_as_owner', function (PartAssociation $t, PartAssociation $o) {
            //We compare the translation keys, as it contains info about the type and other type info
            return $t->getOther() === $o->getOther()
                && $t->getTypeTranslationKey() === $o->getTypeTranslationKey();
        });

        $this->mergeCollections($target, $other, 'orderdetails', function (Orderdetail $t, Orderdetail $o) {
            //First check that the orderdetails infos are equal
            $tmp = $t->getSupplier() === $o->getSupplier()
                && $t->getSupplierPartNr() === $o->getSupplierPartNr()
                && $t->getSupplierProductUrl(false) === $o->getSupplierProductUrl(false);

            if (!$tmp) {
                return false;
            }

            //Check if the pricedetails are equal
            $t_pricedetails = $t->getPricedetails();
            $o_pricedetails = $o->getPricedetails();
            //Ensure that both pricedetails have the same length
            if (count($t_pricedetails) !== count($o_pricedetails)) {
                return false;
            }

            //Check if all pricedetails are equal
            for ($n=0, $nMax = count($t_pricedetails); $n< $nMax; $n++) {
                $t_price = $t_pricedetails->get($n);
                $o_price = $o_pricedetails->get($n);

                if (!$t_price->getPrice()->isEqualTo($o_price->getPrice())
                    || $t_price->getCurrency() !== $o_price->getCurrency()
                    || $t_price->getPriceRelatedQuantity() !== $o_price->getPriceRelatedQuantity()
                    || $t_price->getMinDiscountQuantity() !== $o_price->getMinDiscountQuantity()
                ) {
                    return false;
                }
            }

            //If all pricedetails are equal, the orderdetails are equal
            return true;
        });
        //The pricedetails are not correctly assigned to the new orderdetails, so fix that
        foreach ($target->getOrderdetails() as $orderdetail) {
            foreach ($orderdetail->getPricedetails() as $pricedetail) {
                $pricedetail->setOrderdetail($orderdetail);
            }
        }
    }
}