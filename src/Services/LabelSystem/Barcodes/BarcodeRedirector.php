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

declare(strict_types=1);

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

namespace App\Services\LabelSystem\Barcodes;

use App\Entity\LabelSystem\LabelSupportedElement;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use InvalidArgumentException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @see \App\Tests\Services\LabelSystem\Barcodes\BarcodeRedirectorTest
 */
final class BarcodeRedirector
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator, private readonly EntityManagerInterface $em)
    {
    }

    /**
     * Determines the URL to which the user should be redirected, when scanning a QR code.
     *
     * @param  LocalBarcodeScanResult | VendorBarcodeScanResult  $barcodeScan The result of the barcode scan
     * @return string the URL to which should be redirected
     *
     * @throws EntityNotFoundException
     */
    public function getRedirectURL(LocalBarcodeScanResult | VendorBarcodeScanResult $barcodeScan): string
    {
        if($barcodeScan instanceof LocalBarcodeScanResult) {
            return $this->getURLLocalBarcode($barcodeScan);
        }
        else{
            return $this->getURLVendorBarcode($barcodeScan);
        }
    }

    private function getURLLocalBarcode(LocalBarcodeScanResult $barcodeScan): string
    {
        switch ($barcodeScan->target_type) {
            case LabelSupportedElement::PART:
                return $this->urlGenerator->generate('app_part_show', ['id' => $barcodeScan->target_id]);
            case LabelSupportedElement::PART_LOT:
                //Try to determine the part to the given lot
                $lot = $this->em->find(PartLot::class, $barcodeScan->target_id);
                if (!$lot instanceof PartLot) {
                    throw new EntityNotFoundException();
                }

                return $this->urlGenerator->generate('app_part_show', ['id' => $lot->getPart()->getID()]);

            case LabelSupportedElement::STORELOCATION:
                return $this->urlGenerator->generate('part_list_store_location', ['id' => $barcodeScan->target_id]);

            default:
                throw new InvalidArgumentException('Unknown $type: '.$barcodeScan->target_type->name);
        }
    }

    /**
     * Gets the URL to a part from a scan of a Vendor Barcode
     */
    private function getURLVendorBarcode(VendorBarcodeScanResult $barcodeScan): string
    {
        $part = $this->getPartFromVendor($barcodeScan);
        return $this->urlGenerator->generate('app_part_show', ['id' => $part->getID()]);
    }

    /**
     * Gets a part from a scan of a Vendor Barcode by filtering for parts
     * with the same Info Provider Id or, if that fails, by looking for parts with the
     * same manufacturer product number. Only returns the first matching part.
     */
    private function getPartFromVendor(VendorBarcodeScanResult $barcodeScan) : Part
    {
        // first check via the info provider ID (e.g. Vendor ID). This might fail if the part was not added via
        // the info provider system or if the part was bought from a different vendor than the data was retrieved
        // from.
        $qb = $this->em->getRepository(Part::class)->createQueryBuilder('part');
        //Lower() to be case insensitive
        $qb->where($qb->expr()->like('LOWER(part.providerReference.provider_id)', 'LOWER(:vendor_id)'));
        $qb->setParameter('vendor_id', $barcodeScan->vendor_part_number);
        $results = $qb->getQuery()->getResult();
        if($results){
            return $results[0];
        }

        //Fallback to the manufacturer part number. This may return false positives, since it is common for
        //multiple manufacturers to use the same part number for their version of a common product
        //We assume the user is able to realize when this returns the wrong part
        //If there's barcodes that contain the vendor we could make this more robust, but at least Digikey doesn't.
        $qb = $this->em->getRepository(Part::class)->createQueryBuilder('part');
        $qb->where($qb->expr()->like('LOWER(part.manufacturer_product_number)', 'LOWER(:mpn)'));
        $qb->setParameter('mpn', $barcodeScan->manufacturer_part_number);
        $results = $qb->getQuery()->getResult();
        if($results){
            return $results[0];
        }
        throw new EntityNotFoundException();
    }
}
