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

namespace App\Entity\Parts\PartTraits;

use App\Entity\Parts\ManufacturingStatus;
use Doctrine\DBAL\Types\Types;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\Part;
use App\Validator\Constraints\Selectable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * In this trait all manufacturer related properties of a part are collected (like MPN, manufacturer URL).
 */
trait ManufacturerTrait
{
    /**
     * @var Manufacturer|null The manufacturer of this part
     */
    #[Groups(['simple', 'extended', 'full', 'import', 'part:read', 'part:write'])]
    #[ORM\ManyToOne(targetEntity: Manufacturer::class)]
    #[ORM\JoinColumn(name: 'id_manufacturer')]
    #[Selectable()]
    protected ?Manufacturer $manufacturer = null;

    /**
     * @var string the url to the part on the manufacturer's homepage
     */
    #[Assert\Url]
    #[Groups(['full', 'import', 'part:read', 'part:write'])]
    #[ORM\Column(type: Types::TEXT)]
    protected string $manufacturer_product_url = '';

    /**
     * @var string The product number used by the manufacturer. If this is set to "", the name field is used.
     */
    #[Groups(['extended', 'full', 'import', 'part:read', 'part:write'])]
    #[ORM\Column(type: Types::STRING)]
    protected string $manufacturer_product_number = '';

    /**
     * @var ManufacturingStatus|null The production status of this part. Can be one of the specified ones.
     */
    #[Groups(['extended', 'full', 'import', 'part:read', 'part:write'])]
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, enumType: ManufacturingStatus::class)]
    protected ?ManufacturingStatus $manufacturing_status = ManufacturingStatus::NOT_SET;

    /**
     * Get the link to the website of the article on the manufacturers website
     * When no this part has no explicit url set, then it is tried to generate one from the Manufacturer of this part
     * automatically.
     *
     * @return string the link to the article
     */
    public function getManufacturerProductUrl(): string
    {
        if ('' !== $this->manufacturer_product_url) {
            return $this->manufacturer_product_url;
        }

        if (null !== $this->getManufacturer()) {
            //If possible use the MPN to resolve the auto product URL, otherwise use the parts name
            $mpn = $this->getManufacturerProductNumber();
            if (empty($mpn)) {
                $mpn = $this->getName();
            }
            return $this->getManufacturer()->getAutoProductUrl($mpn);
        }

        return ''; // no url is available
    }

    /**
     * Similar to getManufacturerProductUrl, but here only the database value is returned.
     *
     * @return string the manufacturer url saved in DB for this part
     */
    public function getCustomProductURL(): string
    {
        return $this->manufacturer_product_url;
    }

    /**
     * Returns the manufacturing/production status for this part.
     * The status can be one of the following:
     * (Similar to https://designspark.zendesk.com/hc/en-us/articles/213584805-What-are-the-Lifecycle-Status-definitions-)
     * * "": Status unknown
     * * "announced": Part has been announced, but is not in production yet
     * * "active": Part is in production and will be for the foreseeable future
     * * "nrfnd": Not recommended for new designs.
     * * "eol": Part will become discontinued soon
     * * "discontinued": Part is obsolete/discontinued by the manufacturer.
     *
     * @return ManufacturingStatus|null
     */
    public function getManufacturingStatus(): ?ManufacturingStatus
    {
        return $this->manufacturing_status;
    }

    /**
     * Sets the manufacturing status for this part
     * See getManufacturingStatus() for valid values.
     *
     * @return $this
     */
    public function setManufacturingStatus(ManufacturingStatus $manufacturing_status): self
    {
        $this->manufacturing_status = $manufacturing_status;

        return $this;
    }

    /**
     *  Get the manufacturer of this part (if there is one).
     *
     * @return Manufacturer the manufacturer of this part (if there is one)
     */
    public function getManufacturer(): ?Manufacturer
    {
        return $this->manufacturer;
    }

    /**
     * Returns the assigned manufacturer product number (MPN) for this part.
     */
    public function getManufacturerProductNumber(): string
    {
        return $this->manufacturer_product_number;
    }

    /**
     * Sets the manufacturer product number (MPN) for this part.
     *
     * @return Part
     */
    public function setManufacturerProductNumber(string $manufacturer_product_number): self
    {
        $this->manufacturer_product_number = $manufacturer_product_number;

        return $this;
    }

    /**
     * Sets the URL to the manufacturer site about this Part.
     * Set to "" if this part should use the automatically URL based on its manufacturer.
     *
     * @param string $new_url The new url
     *
     * @return $this
     */
    public function setManufacturerProductURL(string $new_url): self
    {
        $this->manufacturer_product_url = $new_url;

        return $this;
    }

    /**
     * Sets the new manufacturer of this part.
     *
     * @param Manufacturer|null $new_manufacturer The new Manufacturer of this part. Set to null, if this part should
     *                                            not have a manufacturer.
     *
     * @return $this
     */
    public function setManufacturer(?Manufacturer $new_manufacturer): self
    {
        $this->manufacturer = $new_manufacturer;

        return $this;
    }
}
