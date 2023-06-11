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
 */
#[DiscriminatorMap(typeProperty: 'type', mapping: ['attachment_type' => \App\Entity\Attachments\AttachmentType::class, 'attachment' => \App\Entity\Attachments\Attachment::class, 'attachment_type_attachment' => \App\Entity\Attachments\AttachmentTypeAttachment::class, 'category_attachment' => \App\Entity\Attachments\CategoryAttachment::class, 'currency_attachment' => \App\Entity\Attachments\CurrencyAttachment::class, 'footprint_attachment' => \App\Entity\Attachments\FootprintAttachment::class, 'group_attachment' => \App\Entity\Attachments\GroupAttachment::class, 'label_attachment' => \App\Entity\Attachments\LabelAttachment::class, 'manufacturer_attachment' => \App\Entity\Attachments\ManufacturerAttachment::class, 'measurement_unit_attachment' => \App\Entity\Attachments\MeasurementUnitAttachment::class, 'part_attachment' => \App\Entity\Attachments\PartAttachment::class, 'project_attachment' => \App\Entity\Attachments\ProjectAttachment::class, 'storelocation_attachment' => \App\Entity\Attachments\StorelocationAttachment::class, 'supplier_attachment' => \App\Entity\Attachments\SupplierAttachment::class, 'user_attachment' => \App\Entity\Attachments\UserAttachment::class, 'category' => \App\Entity\Parts\Category::class, 'project' => \App\Entity\ProjectSystem\Project::class, 'project_bom_entry' => \App\Entity\ProjectSystem\ProjectBOMEntry::class, 'footprint' => \App\Entity\Parts\Footprint::class, 'group' => \App\Entity\UserSystem\Group::class, 'manufacturer' => \App\Entity\Parts\Manufacturer::class, 'orderdetail' => \App\Entity\PriceInformations\Orderdetail::class, 'part' => \App\Entity\Parts\Part::class, 'pricedetail' => 'App\Entity\PriceInformation\Pricedetail', 'storelocation' => \App\Entity\Parts\Storelocation::class, 'part_lot' => \App\Entity\Parts\PartLot::class, 'currency' => \App\Entity\PriceInformations\Currency::class, 'measurement_unit' => \App\Entity\Parts\MeasurementUnit::class, 'parameter' => 'App\Entity\Parts\AbstractParameter', 'supplier' => \App\Entity\Parts\Supplier::class, 'user' => \App\Entity\UserSystem\User::class])]
#[ORM\MappedSuperclass(repositoryClass: \App\Repository\DBElementRepository::class)]
abstract class AbstractDBElement implements JsonSerializable
{
    /** @var int|null The Identification number for this part. This value is unique for the element in this table.
     * Null if the element is not saved to DB yet.
     */
    #[Groups(['full'])]
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
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
