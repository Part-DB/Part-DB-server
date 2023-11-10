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


namespace App\Services\InfoProviderSystem;

use App\Entity\Attachments\AttachmentType;
use App\Entity\Attachments\PartAttachment;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Parameters\AbstractParameter;
use App\Entity\Parameters\PartParameter;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\InfoProviderReference;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\ManufacturingStatus;
use App\Entity\Parts\Part;
use App\Entity\Parts\Supplier;
use App\Entity\PriceInformations\Currency;
use App\Entity\PriceInformations\Orderdetail;
use App\Entity\PriceInformations\Pricedetail;
use App\Services\InfoProviderSystem\DTOs\FileDTO;
use App\Services\InfoProviderSystem\DTOs\ParameterDTO;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOs\PriceDTO;
use App\Services\InfoProviderSystem\DTOs\PurchaseInfoDTO;
use Brick\Math\BigDecimal;
use Doctrine\ORM\EntityManagerInterface;

/**
 * This class converts DTOs to entities which can be persisted in the DB
 */
final class DTOtoEntityConverter
{
    private const TYPE_DATASHEETS_NAME = 'Datasheet';
    private const TYPE_IMAGE_NAME = 'Image';

    public function __construct(private readonly EntityManagerInterface $em, private readonly string $base_currency)
    {
    }

    /**
     * Converts the given DTO to a PartParameter entity.
     * @param  ParameterDTO  $dto
     * @param  PartParameter  $entity The entity to apply the DTO on. If null a new entity will be created
     * @return PartParameter
     */
    public function convertParameter(ParameterDTO $dto, PartParameter $entity = new PartParameter()): PartParameter
    {
        $entity->setName($dto->name);
        $entity->setValueText($dto->value_text ?? '');
        $entity->setValueTypical($dto->value_typ);
        $entity->setValueMin($dto->value_min);
        $entity->setValueMax($dto->value_max);
        $entity->setUnit($dto->unit ?? '');
        $entity->setSymbol($dto->symbol ?? '');
        $entity->setGroup($dto->group ?? '');

        return $entity;
    }

    /**
     * Converts the given DTO to a Pricedetail entity.
     * @param  PriceDTO  $dto
     * @param  Pricedetail  $entity
     * @return Pricedetail
     */
    public function convertPrice(PriceDTO $dto, Pricedetail $entity = new Pricedetail()): Pricedetail
    {
        $entity->setMinDiscountQuantity($dto->minimum_discount_amount);
        $entity->setPrice($dto->getPriceAsBigDecimal());
        $entity->setPriceRelatedQuantity($dto->price_related_quantity);

        //Currency TODO
        if ($dto->currency_iso_code !== null) {
            $entity->setCurrency($this->getCurrency($dto->currency_iso_code));
        } else {
            $entity->setCurrency(null);
        }

        return $entity;
    }

    /**
     * Converts the given DTO to an orderdetail entity.
     */
    public function convertPurchaseInfo(PurchaseInfoDTO $dto, Orderdetail $entity = new Orderdetail()): Orderdetail
    {
        $entity->setSupplierpartnr($dto->order_number);
        $entity->setSupplierProductUrl($dto->product_url ?? '');

        $entity->setSupplier($this->getOrCreateEntityNonNull(Supplier::class, $dto->distributor_name));
        foreach ($dto->prices as $price) {
            $entity->addPricedetail($this->convertPrice($price));
        }

        return $entity;
    }

    /**
     * Converts the given DTO to an Attachment entity.
     * @param  FileDTO  $dto
     * @param  AttachmentType  $type The type which should be used for the attachment
     * @param  PartAttachment  $entity
     * @return PartAttachment
     */
    public function convertFile(FileDTO $dto, AttachmentType $type, PartAttachment $entity = new PartAttachment()): PartAttachment
    {
        $entity->setURL($dto->url);

        $entity->setAttachmentType($type);

        //If no name is given, try to extract the name from the URL
        if (empty($dto->name)) {
            $entity->setName($this->getAttachmentNameFromURL($dto->url));
        } else {
            $entity->setName($dto->name);
        }

        return $entity;
    }

    private function getAttachmentNameFromURL(string $url): string
    {
        return basename(parse_url($url, PHP_URL_PATH));
    }

    /**
     * Converts a PartDetailDTO to a Part entity
     * @param  PartDetailDTO  $dto
     * @param  Part  $entity The part entity to fill
     * @return Part
     */
    public function convertPart(PartDetailDTO $dto, Part $entity = new Part()): Part
    {
        $entity->setName($dto->name);
        $entity->setDescription($dto->description ?? '');
        $entity->setComment($dto->notes ?? '');

        $entity->setMass($dto->mass);

        $entity->setManufacturer($this->getOrCreateEntity(Manufacturer::class, $dto->manufacturer));
        $entity->setFootprint($this->getOrCreateEntity(Footprint::class, $dto->footprint));

        $entity->setManufacturerProductNumber($dto->mpn ?? '');
        $entity->setManufacturingStatus($dto->manufacturing_status ?? ManufacturingStatus::NOT_SET);
        $entity->setManufacturerProductURL($dto->manufacturer_product_url ?? '');

        //Set the provider reference on the part
        $entity->setProviderReference(InfoProviderReference::fromPartDTO($dto));

        //Add parameters
        foreach ($dto->parameters ?? [] as $parameter) {
            $entity->addParameter($this->convertParameter($parameter));
        }

        //Add preview image
        $image_type = $this->getImageType();

        if ($dto->preview_image_url) {
            $preview_image = new PartAttachment();
            $preview_image->setURL($dto->preview_image_url);
            $preview_image->setName('Main image');
            $preview_image->setAttachmentType($image_type);

            $entity->addAttachment($preview_image);
            $entity->setMasterPictureAttachment($preview_image);
        }

        //Add other images
        foreach ($dto->images ?? [] as $image) {
            //Ensure that the image is not the same as the preview image
            if ($image->url === $dto->preview_image_url) {
                continue;
            }

            $entity->addAttachment($this->convertFile($image, $image_type));
        }


        //Add datasheets
        $datasheet_type = $this->getDatasheetType();
        foreach ($dto->datasheets ?? [] as $datasheet) {
            $entity->addAttachment($this->convertFile($datasheet, $datasheet_type));
        }

        //Add orderdetails and prices
        foreach ($dto->vendor_infos ?? [] as $vendor_info) {
            $entity->addOrderdetail($this->convertPurchaseInfo($vendor_info));
        }

        return $entity;
    }

    /**
     * Get the existing entity of the given class with the given name or create it if it does not exist.
     * If the name is null, null is returned.
     * @template T of AbstractStructuralDBElement
     * @param  string  $class
     * @phpstan-param class-string<T> $class
     * @param  string|null  $name
     * @return AbstractStructuralDBElement|null
     * @phpstan-return T|null
     */
    private function getOrCreateEntity(string $class, ?string $name): ?AbstractStructuralDBElement
    {
        //Fall through to make converting easier
        if ($name === null) {
            return null;
        }

        return $this->getOrCreateEntityNonNull($class, $name);
    }

    /**
     * Get the existing entity of the given class with the given name or create it if it does not exist.
     * @template T of AbstractStructuralDBElement
     * @param  string  $class The class of the entity to create
     * @phpstan-param class-string<T> $class
     * @param  string  $name The name of the entity to create
     * @return AbstractStructuralDBElement
     * @phpstan-return T
     */
    private function getOrCreateEntityNonNull(string $class, string $name): AbstractStructuralDBElement
    {
        return $this->em->getRepository($class)->findOrCreateForInfoProvider($name);
    }

    /**
     * Returns the currency entity for the given ISO code or create it if it does not exist
     * @param  string  $iso_code
     * @return Currency|null
     */
    private function getCurrency(string $iso_code): ?Currency
    {
        //Check if the currency is the base currency (then we can just return null)
        if ($iso_code === $this->base_currency) {
            return null;
        }

        return $this->em->getRepository(Currency::class)->findOrCreateByISOCode($iso_code);
    }

    /**
     * Returns the attachment type used for datasheets or creates it if it does not exist
     * @return AttachmentType
     */
    private function getDatasheetType(): AttachmentType
    {
        /** @var AttachmentType $tmp */
        $tmp = $this->em->getRepository(AttachmentType::class)->findOrCreateForInfoProvider(self::TYPE_DATASHEETS_NAME);

        //If the entity was newly created, set the file filter
        if ($tmp->getID() === null) {
            $tmp->setFiletypeFilter('application/pdf');
            $tmp->setAlternativeNames(self::TYPE_DATASHEETS_NAME);
        }

        return $tmp;
    }

    /**
     * Returns the attachment type used for datasheets or creates it if it does not exist
     * @return AttachmentType
     */
    private function getImageType(): AttachmentType
    {
        /** @var AttachmentType $tmp */
        $tmp = $this->em->getRepository(AttachmentType::class)->findOrCreateForInfoProvider(self::TYPE_IMAGE_NAME);

        //If the entity was newly created, set the file filter
        if ($tmp->getID() === null) {
            $tmp->setFiletypeFilter('image/*');
            $tmp->setAlternativeNames(self::TYPE_IMAGE_NAME);
        }

        return $tmp;
    }

}