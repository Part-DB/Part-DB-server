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

namespace App\Entity\Base;

use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use Symfony\Component\Serializer\Annotation\DiscriminatorMap;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * This class is for managing all database objects.
 *
 * You should use this class for ALL classes which manages database records!
 *          (except special tables like "internal"...)
 * Every database table which are managed with this class (or a subclass of it)
 *          must have the table row "id"!! The ID is the unique key to identify the elements.
 *
 * @ORM\MappedSuperclass(repositoryClass="App\Repository\DBElementRepository")
 */
#[DiscriminatorMap(typeProperty: 'type', mapping: ['attachment_type' => 'App\Entity\Attachments\AttachmentType', 'attachment' => 'App\Entity\Attachments\Attachment', 'attachment_type_attachment' => 'App\Entity\Attachments\AttachmentTypeAttachment', 'category_attachment' => 'App\Entity\Attachments\CategoryAttachment', 'currency_attachment' => 'App\Entity\Attachments\CurrencyAttachment', 'footprint_attachment' => 'App\Entity\Attachments\FootprintAttachment', 'group_attachment' => 'App\Entity\Attachments\GroupAttachment', 'label_attachment' => 'App\Entity\Attachments\LabelAttachment', 'manufacturer_attachment' => 'App\Entity\Attachments\ManufacturerAttachment', 'measurement_unit_attachment' => 'App\Entity\Attachments\MeasurementUnitAttachment', 'part_attachment' => 'App\Entity\Attachments\PartAttachment', 'project_attachment' => 'App\Entity\Attachments\ProjectAttachment', 'storelocation_attachment' => 'App\Entity\Attachments\StorelocationAttachment', 'supplier_attachment' => 'App\Entity\Attachments\SupplierAttachment', 'user_attachment' => 'App\Entity\Attachments\UserAttachment', 'category' => 'App\Entity\Parts\Category', 'project' => 'App\Entity\ProjectSystem\Project', 'project_bom_entry' => 'App\Entity\ProjectSystem\ProjectBOMEntry', 'footprint' => 'App\Entity\Parts\Footprint', 'group' => 'App\Entity\UserSystem\Group', 'manufacturer' => 'App\Entity\Parts\Manufacturer', 'orderdetail' => 'App\Entity\PriceInformations\Orderdetail', 'part' => 'App\Entity\Parts\Part', 'pricedetail' => 'App\Entity\PriceInformation\Pricedetail', 'storelocation' => 'App\Entity\Parts\Storelocation', 'part_lot' => 'App\Entity\Parts\PartLot', 'currency' => 'App\Entity\PriceInformations\Currency', 'measurement_unit' => 'App\Entity\Parts\MeasurementUnit', 'parameter' => 'App\Entity\Parts\AbstractParameter', 'supplier' => 'App\Entity\Parts\Supplier', 'user' => 'App\Entity\UserSystem\User'])]
abstract class AbstractDBElement implements JsonSerializable
{
    /** @var int|null The Identification number for this part. This value is unique for the element in this table.
     * Null if the element is not saved to DB yet.
     * @ORM\Column(type="integer")
     * @ORM\Id()
     * @ORM\GeneratedValue()
     */
    #[Groups(['full'])]
    protected ?int $id = null;

    public function __clone()
    {
        if ($this->id) {
            //Set ID to null, so that an new entry is created
            $this->id = null;
        }
    }

    /**
     * Get the ID. The ID can be zero, or even negative (for virtual elements). If an element is virtual, can be
     * checked with isVirtualElement().
     *
     * Returns null, if the element is not saved to the DB yet.
     *
     * @return int|null the ID of this element
     */
    public function getID(): ?int
    {
        return $this->id;
    }

    public function jsonSerialize(): array
    {
        return ['@id' => $this->getID()];
    }
}
