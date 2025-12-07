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

use App\Entity\Attachments\AttachmentType;
use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\AttachmentTypeAttachment;
use App\Entity\Attachments\CategoryAttachment;
use App\Entity\Attachments\CurrencyAttachment;
use App\Entity\Attachments\FootprintAttachment;
use App\Entity\Attachments\GroupAttachment;
use App\Entity\Attachments\LabelAttachment;
use App\Entity\Attachments\ManufacturerAttachment;
use App\Entity\Attachments\MeasurementUnitAttachment;
use App\Entity\Attachments\PartAttachment;
use App\Entity\Attachments\ProjectAttachment;
use App\Entity\Attachments\StorageLocationAttachment;
use App\Entity\Attachments\SupplierAttachment;
use App\Entity\Attachments\UserAttachment;
use App\Entity\Parameters\AbstractParameter;
use App\Entity\Parts\Category;
use App\Entity\PriceInformations\Pricedetail;
use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Entity\Parts\Footprint;
use App\Entity\UserSystem\Group;
use App\Entity\Parts\Manufacturer;
use App\Entity\PriceInformations\Orderdetail;
use App\Entity\Parts\Part;
use App\Entity\Parts\StorageLocation;
use App\Entity\Parts\PartLot;
use App\Entity\PriceInformations\Currency;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Supplier;
use App\Entity\UserSystem\User;
use App\Repository\DBElementRepository;
use Doctrine\DBAL\Types\Types;
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
#[DiscriminatorMap(typeProperty: 'type', mapping: ['attachment_type' => AttachmentType::class, 'attachment' => Attachment::class, 'attachment_type_attachment' => AttachmentTypeAttachment::class, 'category_attachment' => CategoryAttachment::class, 'currency_attachment' => CurrencyAttachment::class, 'footprint_attachment' => FootprintAttachment::class, 'group_attachment' => GroupAttachment::class, 'label_attachment' => LabelAttachment::class, 'manufacturer_attachment' => ManufacturerAttachment::class, 'measurement_unit_attachment' => MeasurementUnitAttachment::class, 'part_attachment' => PartAttachment::class, 'project_attachment' => ProjectAttachment::class, 'storelocation_attachment' => StorageLocationAttachment::class, 'supplier_attachment' => SupplierAttachment::class, 'user_attachment' => UserAttachment::class, 'category' => Category::class, 'project' => Project::class, 'project_bom_entry' => ProjectBOMEntry::class, 'footprint' => Footprint::class, 'group' => Group::class, 'manufacturer' => Manufacturer::class, 'orderdetail' => Orderdetail::class, 'part' => Part::class, 'pricedetail' => Pricedetail::class, 'storelocation' => StorageLocation::class, 'part_lot' => PartLot::class, 'currency' => Currency::class, 'measurement_unit' => MeasurementUnit::class, 'parameter' => AbstractParameter::class, 'supplier' => Supplier::class, 'user' => User::class])]
#[ORM\MappedSuperclass(repositoryClass: DBElementRepository::class)]
abstract class AbstractDBElement implements JsonSerializable
{
    /** @var int|null The Identification number for this part. This value is unique for the element in this table.
     * Null if the element is not saved to DB yet.
     */
    #[Groups(['full', 'api:basic:read'])]
    #[ORM\Column(type: Types::INTEGER)]
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
