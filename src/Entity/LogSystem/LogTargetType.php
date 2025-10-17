<?php

declare(strict_types=1);

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
namespace App\Entity\LogSystem;

use App\Entity\AssemblySystem\Assembly;
use App\Entity\AssemblySystem\AssemblyBOMEntry;
use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\AttachmentType;
use App\Entity\InfoProviderSystem\BulkInfoProviderImportJob;
use App\Entity\InfoProviderSystem\BulkInfoProviderImportJobPart;
use App\Entity\LabelSystem\LabelProfile;
use App\Entity\Parameters\AbstractParameter;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartAssociation;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\StorageLocation;
use App\Entity\Parts\Supplier;
use App\Entity\PriceInformations\Currency;
use App\Entity\PriceInformations\Orderdetail;
use App\Entity\PriceInformations\Pricedetail;
use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\User;

enum LogTargetType: int
{
    case NONE = 0;
    case USER = 1;
    case ATTACHMENT = 2;
    case ATTACHMENT_TYPE = 3;
    case CATEGORY = 4;
    case PROJECT = 5;
    case BOM_ENTRY = 6;
    case FOOTPRINT = 7;
    case GROUP = 8;
    case MANUFACTURER = 9;
    case PART = 10;
    case STORELOCATION = 11;
    case SUPPLIER = 12;
    case PART_LOT = 13;
    case CURRENCY = 14;
    case ORDERDETAIL = 15;
    case PRICEDETAIL = 16;
    case MEASUREMENT_UNIT = 17;
    case PARAMETER = 18;
    case LABEL_PROFILE = 19;

    case PART_ASSOCIATION = 20;
    case BULK_INFO_PROVIDER_IMPORT_JOB = 21;
    case BULK_INFO_PROVIDER_IMPORT_JOB_PART = 22;

    case ASSEMBLY = 23;
    case ASSEMBLY_BOM_ENTRY = 24;

    /**
     * Returns the class name of the target type or null if the target type is NONE.
     * @return string|null
     */
    public function toClass(): ?string
    {
        return match ($this) {
            self::NONE => null,
            self::USER => User::class,
            self::ATTACHMENT => Attachment::class,
            self::ATTACHMENT_TYPE => AttachmentType::class,
            self::CATEGORY => Category::class,
            self::PROJECT => Project::class,
            self::BOM_ENTRY => ProjectBOMEntry::class,
            self::ASSEMBLY => Assembly::class,
            self::ASSEMBLY_BOM_ENTRY => AssemblyBOMEntry::class,
            self::FOOTPRINT => Footprint::class,
            self::GROUP => Group::class,
            self::MANUFACTURER => Manufacturer::class,
            self::PART => Part::class,
            self::STORELOCATION => StorageLocation::class,
            self::SUPPLIER => Supplier::class,
            self::PART_LOT => PartLot::class,
            self::CURRENCY => Currency::class,
            self::ORDERDETAIL => Orderdetail::class,
            self::PRICEDETAIL => Pricedetail::class,
            self::MEASUREMENT_UNIT => MeasurementUnit::class,
            self::PARAMETER => AbstractParameter::class,
            self::LABEL_PROFILE => LabelProfile::class,
            self::PART_ASSOCIATION => PartAssociation::class,
            self::BULK_INFO_PROVIDER_IMPORT_JOB => BulkInfoProviderImportJob::class,
            self::BULK_INFO_PROVIDER_IMPORT_JOB_PART => BulkInfoProviderImportJobPart::class,
        };
    }

    /**
     * Determines the target type from the given class name or object.
     * @param  object|string  $element
     * @phpstan-param object|class-string $element
     * @return self
     */
    public static function fromElementClass(object|string $element): self
    {
        //Iterate over all possible types
        foreach (self::cases() as $case) {
            $class = $case->toClass();

            //Skip NONE
            if ($class === null) {
                continue;
            }

            //Check if the given element is a instance of the class
            if (is_a($element, $class, true)) {
                return $case;
            }
        }

        $elementClass = is_object($element) ? $element::class : $element;
        //If no matching type was found, throw an exception
        throw new \InvalidArgumentException("The given class $elementClass is not a valid log target type.");
    }
}
