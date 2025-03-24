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

use App\Entity\Parts\InfoProviderReference;
use App\Entity\Parts\ManufacturingStatus;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartAssociation;
use App\Entity\PriceInformations\Orderdetail;

/**
 * This class merges two parts together.
 *
 * @implements EntityMergerInterface<Part>
 * @see \App\Tests\Services\EntityMergers\Mergers\PartMergerTest
 */
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

        //Merge basic fields
        $this->mergeTextWithSeparator($target, $other, 'name');
        $this->mergeTextWithSeparator($target, $other, 'description');
        $this->mergeComment($target, $other);
        $this->useOtherValueIfNotEmtpy($target, $other, 'manufacturer_product_url');
        $this->useOtherValueIfNotEmtpy($target, $other, 'manufacturer_product_number');
        $this->useOtherValueIfNotEmtpy($target, $other, 'mass');
        $this->useOtherValueIfNotEmtpy($target, $other, 'ipn');

        //Merge relations to other entities
        $this->useOtherValueIfNotNull($target, $other, 'manufacturer');
        $this->useOtherValueIfNotNull($target, $other, 'footprint');
        $this->useOtherValueIfNotNull($target, $other, 'category');
        $this->useOtherValueIfNotNull($target, $other, 'partUnit');
        $this->useOtherValueIfNotNull($target, $other, 'partCustomState');

        //We assume that the higher value is the correct one for minimum instock
        $this->useLargerValue($target, $other, 'minamount');

        //We assume that a part needs review and is a favorite if one of the parts is
        $this->useTrueValue($target, $other, 'needs_review');
        $this->useTrueValue($target, $other, 'favorite');

        //Merge the tags using the tag merger
        $this->mergeTags($target, $other, 'tags');

        //Merge manufacturing status
        $this->useCallback(function (?ManufacturingStatus $t, ?ManufacturingStatus $o): ManufacturingStatus {
            //Use the other value, if the target value is not set
            if ($t === ManufacturingStatus::NOT_SET || $t === null) {
                return $o ?? ManufacturingStatus::NOT_SET;
            }

            return $t;
        }, $target, $other, 'manufacturing_status');

        //Merge provider reference
        $this->useCallback(function (InfoProviderReference $t, InfoProviderReference $o): InfoProviderReference {
            if (!$t->isProviderCreated() && $o->isProviderCreated()) {
                return $o;
            }
            return $t;
        }, $target, $other, 'providerReference');

        //Merge the collections
        $this->mergeCollectionFields($target, $other, $context);

        return $target;
    }

    private function comparePartAssociations(PartAssociation $t, PartAssociation $o): bool
    {
        //We compare the translation keys, as it contains info about the type and other type info
        return $t->getOther() === $o->getOther()
            && $t->getTypeTranslationKey() === $o->getTypeTranslationKey();
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

        //Merge the associations
        $this->mergeCollections($target, $other, 'associated_parts_as_owner', $this->comparePartAssociations(...));

        //We have to recreate the associations towards the other part, as they are not created by the merger
        foreach ($other->getAssociatedPartsAsOther() as $association) {
            //Clone the association
            $clone = clone $association;
            //Set the target part as the other part
            $clone->setOther($target);
            $owner = $clone->getOwner();
            if (!$owner) {
                continue;
            }
            //Ensure that the association is not already present
            foreach ($owner->getAssociatedPartsAsOwner() as $existing_association) {
                if ($this->comparePartAssociations($existing_association, $clone)) {
                    continue 2;
                }
            }

            //Add the association to the owner
            $owner->addAssociatedPartsAsOwner($clone);
        }

        // Merge orderdetails, considering same supplier+part number as duplicates
        $this->mergeCollections($target, $other, 'orderdetails', function (Orderdetail $t, Orderdetail $o) {
            // If supplier and part number match, merge the orderdetails
            if ($t->getSupplier() === $o->getSupplier() && $t->getSupplierPartNr() === $o->getSupplierPartNr()) {
                // Update URL if target doesn't have one
                if (empty($t->getSupplierProductUrl(false)) && !empty($o->getSupplierProductUrl(false))) {
                    $t->setSupplierProductUrl($o->getSupplierProductUrl(false));
                }
                // Merge price details: add new ones, update empty ones, keep existing non-empty ones
                foreach ($o->getPricedetails() as $otherPrice) {
                    $found = false;
                    foreach ($t->getPricedetails() as $targetPrice) {
                        if ($targetPrice->getMinDiscountQuantity() === $otherPrice->getMinDiscountQuantity()
                            && $targetPrice->getCurrency() === $otherPrice->getCurrency()) {
                            // Only update price if the existing one is zero/empty (most logical)
                            if ($targetPrice->getPrice()->isZero()) {
                                $targetPrice->setPrice($otherPrice->getPrice());
                                $targetPrice->setPriceRelatedQuantity($otherPrice->getPriceRelatedQuantity());
                            }
                            $found = true;
                            break;
                        }
                    }
                    // Add completely new price tiers
                    if (!$found) {
                        $clonedPrice = clone $otherPrice;
                        $clonedPrice->setOrderdetail($t);
                        $t->addPricedetail($clonedPrice);
                    }
                }
                return true; // Consider them equal so the other one gets skipped
            }
            return false; // Different supplier/part number, add as new
        });
        //The pricedetails are not correctly assigned to the new orderdetails, so fix that
        foreach ($target->getOrderdetails() as $orderdetail) {
            foreach ($orderdetail->getPricedetails() as $pricedetail) {
                $pricedetail->setOrderdetail($orderdetail);
            }
        }
    }
}