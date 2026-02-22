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
use App\Entity\Parts\StorageLocation;
use App\Exceptions\InfoProviderNotActiveException;
use App\Repository\Parts\PartRepository;
use App\Services\InfoProviderSystem\PartInfoRetriever;
use App\Services\InfoProviderSystem\ProviderRegistry;
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
    public function __construct(private UrlGeneratorInterface $urlGenerator, private EntityManagerInterface $em, private PartInfoRetriever $infoRetriever,
        private ProviderRegistry $providerRegistry)
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
        //For other barcodes try to resolve the part first and then redirect to the part page
        $entity = $this->resolveEntity($barcodeScan);

        if ($entity === null) {
            throw new EntityNotFoundException("No entity could be resolved for the given barcode scan result");
        }

        if ($entity instanceof Part) {
            return $this->urlGenerator->generate('app_part_show', ['id' => $entity->getID()]);
        }

        if ($entity instanceof PartLot) {
            return $this->urlGenerator->generate('app_part_show', ['id' => $entity->getPart()->getID(), 'highlightLot' => $entity->getID()]);
        }

        if ($entity instanceof StorageLocation) {
            return $this->urlGenerator->generate('part_list_store_location', ['id' => $entity->getID()]);
        }

        //@phpstan-ignore-next-line This should never happen, since resolveEntity should only return Part, PartLot or StorageLocation
        throw new \LogicException("Resolved entity is of unknown type: ".get_class($entity));
    }

    /**
     * Returns a URL to create a new part based on this barcode scan result, if possible.
     * @param  BarcodeScanResultInterface  $scanResult
     * @return string|null
     * @throws InfoProviderNotActiveException If the scan result contains information for a provider which is currently not active in the system
     */
    public function getCreationURL(BarcodeScanResultInterface $scanResult): ?string
    {
        $infos = $this->getCreateInfos($scanResult);
        if ($infos === null) {
            return null;
        }

        //Ensure that the provider is active, otherwise we should not generate a creation URL for it
        $provider = $this->providerRegistry->getProviderByKey($infos['providerKey']);
        if (!$provider->isActive()) {
            throw InfoProviderNotActiveException::fromProvider($provider);
        }

        return $this->urlGenerator->generate('info_providers_create_part', ['providerKey' => $infos['providerKey'], 'providerId' => $infos['providerId']]);
    }

    /**
     * Tries to resolve the given barcode scan result to a local entity. This can be a Part, a PartLot or a StorageLocation, depending on the type of the barcode and the information contained in it.
     * Returns null if no matching entity could be found.
     * @param  BarcodeScanResultInterface  $barcodeScan
     * @return Part|PartLot|StorageLocation|null
     */
    public function resolveEntity(BarcodeScanResultInterface $barcodeScan): Part|PartLot|StorageLocation|null
    {
        if ($barcodeScan instanceof LocalBarcodeScanResult) {
            return $this->resolvePartFromLocal($barcodeScan);
        }

        if ($barcodeScan instanceof EIGP114BarcodeScanResult) {
            return $this->resolvePartFromVendor($barcodeScan);
        }

        if ($barcodeScan instanceof GTINBarcodeScanResult) {
            return $this->em->getRepository(Part::class)->findOneBy(['gtin' => $barcodeScan->gtin]);
        }

        if ($barcodeScan instanceof LCSCBarcodeScanResult) {
            return $this->resolvePartFromLCSC($barcodeScan);
        }

        throw new \InvalidArgumentException("Barcode does not support resolving to a local entity: ".get_class($barcodeScan));
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
        $entity = $this->resolveEntity($barcodeScan);
        if ($entity instanceof Part) {
            return $entity;
        }
        if ($entity instanceof PartLot) {
            return $entity->getPart();
        }
        //Storage locations are not associated with a specific part, so we cannot resolve a part for
        //a storage location barcode
        return null;
    }

    private function resolvePartFromLocal(LocalBarcodeScanResult $barcodeScan): Part|PartLot|StorageLocation|null
    {
        return match ($barcodeScan->target_type) {
            LabelSupportedElement::PART => $this->em->find(Part::class, $barcodeScan->target_id),
            LabelSupportedElement::PART_LOT => $this->em->find(PartLot::class, $barcodeScan->target_id),
            LabelSupportedElement::STORELOCATION => $this->em->find(StorageLocation::class, $barcodeScan->target_id),
        };
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


    /**
     * Tries to extract creation information for a part from the given barcode scan result. This can be used to
     * automatically fill in the info provider reference of a part, when creating a new part based on the scan result.
     * Returns null if no provider information could be extracted from the scan result, or if the scan result type is unknown and cannot be handled by this function.
     * It is not necessarily checked that the provider is active, or that the result actually exists on the provider side.
     * @param  BarcodeScanResultInterface  $scanResult
     * @return array{providerKey: string, providerId: string}|null
     * @throws InfoProviderNotActiveException If the scan result contains information for a provider which is currently not active in the system
     */
    public function getCreateInfos(BarcodeScanResultInterface $scanResult): ?array
    {
        // LCSC
        if ($scanResult instanceof LCSCBarcodeScanResult) {
            return [
                'providerKey' => 'lcsc',
                'providerId' => $scanResult->lcscCode,
            ];
        }

        if ($scanResult instanceof EIGP114BarcodeScanResult) {
            return $this->getCreationInfoForEIGP114($scanResult);
        }

        return null;

    }

    /**
     * @param  EIGP114BarcodeScanResult  $scanResult
     * @return array{providerKey: string, providerId: string}|null
     */
    private function getCreationInfoForEIGP114(EIGP114BarcodeScanResult $scanResult): ?array
    {
        $vendor = $scanResult->guessBarcodeVendor();

        // Mouser: use supplierPartNumber -> search provider -> provider_id
        if ($vendor === 'mouser' && $scanResult->supplierPartNumber !== null
        ) {
                // Search Mouser using the MPN
                $dtos = $this->infoRetriever->searchByKeyword(
                    keyword: $scanResult->supplierPartNumber,
                    providers: ["mouser"]
                );

                // If there are results, provider_id is MouserPartNumber (per MouserProvider.php)
                $best = $dtos[0] ?? null;

                if ($best !== null) {
                    return [
                        'providerKey' => 'mouser',
                        'providerId' => $best->provider_id,
                    ];
                }

                return null;
        }

        // Digi-Key: can use customerPartNumber or supplierPartNumber directly
        if ($vendor === 'digikey') {
            return [
                'providerKey' => 'digikey',
                'providerId' => $scanResult->customerPartNumber ?? $scanResult->supplierPartNumber,
            ];
        }

        // Element14: can use supplierPartNumber directly
        if ($vendor === 'element14') {
            return [
                'providerKey' => 'element14',
                'providerId' => $scanResult->supplierPartNumber,
            ];
        }

        return null;
    }


}
