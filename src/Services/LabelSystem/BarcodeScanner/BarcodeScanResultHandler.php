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

namespace App\Services\LabelSystem\BarcodeScanner;

use App\Entity\LabelSystem\LabelSupportedElement;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Repository\Parts\PartRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use InvalidArgumentException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * This class handles the result of a barcode scan and determines further actions, like which URL the user should be redirected to.
 *
 * @see \App\Tests\Services\LabelSystem\Barcodes\BarcodeRedirectorTest
 */
final readonly class BarcodeScanResultHandler
{
    public function __construct(private UrlGeneratorInterface $urlGenerator, private EntityManagerInterface $em)
    {
    }

    /**
     * Determines the URL to which the user should be redirected, when scanning a QR code.
     *
     * @param  BarcodeScanResultInterface  $barcodeScan The result of the barcode scan
     * @return string the URL to which should be redirected
     *
     * @throws EntityNotFoundException
     */
    public function getInfoURL(BarcodeScanResultInterface $barcodeScan): string
    {
        //For our internal barcode format we can directly determine the target without looking up the part
        //Also here we can encounter different types of barcodes, like storage location barcodes, which are not resolvable to a part
        if($barcodeScan instanceof LocalBarcodeScanResult) {
            return $this->getURLLocalBarcode($barcodeScan);
        }

        //For other barcodes try to resolve the part first and then redirect to the part page
        $localPart = $this->resolvePart($barcodeScan);
        if ($localPart !== null) {
            return $this->urlGenerator->generate('app_part_show', ['id' => $localPart->getID()]);
        }

        throw new EntityNotFoundException('Could not resolve a local part for the given barcode scan result');
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

                return $this->urlGenerator->generate('app_part_show', ['id' => $lot->getPart()->getID(), 'highlightLot' => $lot->getID()]);

            case LabelSupportedElement::STORELOCATION:
                return $this->urlGenerator->generate('part_list_store_location', ['id' => $barcodeScan->target_id]);

            default:
                throw new InvalidArgumentException('Unknown $type: '.$barcodeScan->target_type->name);
        }
    }

    /**
     * Tries to resolve a Part from the given barcode scan result. Returns null if no part could be found for the given barcode,
     * or the barcode doesn't contain information allowing to resolve to a local part.
     * @param  BarcodeScanResultInterface  $barcodeScan
     * @return Part|null
     * @throws \InvalidArgumentException if the barcode scan result type is unknown and cannot be handled this function
     */
    public function resolvePart(BarcodeScanResultInterface $barcodeScan): ?Part
    {
        if ($barcodeScan instanceof LocalBarcodeScanResult) {
            return $this->resolvePartFromLocal($barcodeScan);
        }

        if ($barcodeScan instanceof EIGP114BarcodeScanResult) {
            return $this->resolvePartFromVendor($barcodeScan);
        }

        if ($barcodeScan instanceof GTINBarcodeScanResult) {
            return $this->resolvePartFromGTIN($barcodeScan);
        }

        if ($barcodeScan instanceof LCSCBarcodeScanResult) {
            return $this->resolvePartFromLCSC($barcodeScan);
        }

        throw new \InvalidArgumentException("Unknown barcode scan result type: ".get_class($barcodeScan));
    }

    private function resolvePartFromLocal(LocalBarcodeScanResult $barcodeScan): ?Part
    {
        switch ($barcodeScan->target_type) {
            case LabelSupportedElement::PART:
                $part = $this->em->find(Part::class, $barcodeScan->target_id);
                return $part instanceof Part ? $part : null;

            case LabelSupportedElement::PART_LOT:
                $lot = $this->em->find(PartLot::class, $barcodeScan->target_id);
                if (!$lot instanceof PartLot) {
                    return null;
                }
                return $lot->getPart();

            default:
                // STORELOCATION etc. doesn't map to a Part
                return null;
        }
    }

    /**
     * Gets a part from a scan of a Vendor Barcode by filtering for parts
     * with the same Info Provider Id or, if that fails, by looking for parts with a
     * matching manufacturer product number. Only returns the first matching part.
     */
    private function resolvePartFromVendor(EIGP114BarcodeScanResult $barcodeScan) : ?Part
    {
        // first check via the info provider ID (e.g. Vendor ID). This might fail if the part was not added via
        // the info provider system or if the part was bought from a different vendor than the data was retrieved
        // from.
        if($barcodeScan->digikeyPartNumber) {

            $part = $this->em->getRepository(Part::class)->getPartByProviderInfo($barcodeScan->digikeyPartNumber);
            if ($part !== null) {
                return $part;
            }
        }

        if (!$barcodeScan->supplierPartNumber){
            return null;
        }

        //Fallback to the manufacturer part number. This may return false positives, since it is common for
        //multiple manufacturers to use the same part number for their version of a common product
        //We assume the user is able to realize when this returns the wrong part
        //If the barcode specifies the manufacturer we try to use that as well

        return $this->em->getRepository(Part::class)->getPartByMPN($barcodeScan->supplierPartNumber, $barcodeScan->mouserManufacturer);
    }

    /**
     * Resolve LCSC barcode -> Part.
     * Strategy:
     *  1) Try providerReference.provider_id == pc (LCSC "Cxxxxxx") if you store it there
     *  2) Fallback to manufacturer_product_number == pm (MPN)
     * Returns first match (consistent with EIGP114 logic)
     */
    private function resolvePartFromLCSC(LCSCBarcodeScanResult $barcodeScan): ?Part
    {
        // Try LCSC code (pc) as provider id if available
        $pc = $barcodeScan->lcscCode; // e.g. C138033
        if ($pc) {
            $part = $this->em->getRepository(Part::class)->getPartByProviderInfo($pc);
            if ($part !== null) {
                return $part;
            }
        }

        // Fallback to MPN (pm)
        $pm = $barcodeScan->mpn; // e.g. RC0402FR-071ML
        if (!$pm) {
            return null;
        }

        return $this->em->getRepository(Part::class)->getPartByMPN($pm);
    }

    private function resolvePartFromGTIN(GTINBarcodeScanResult $barcodeScan): ?Part
    {
        return $this->em->getRepository(Part::class)->findOneBy(['gtin' => $barcodeScan->gtin]);
    }



}
