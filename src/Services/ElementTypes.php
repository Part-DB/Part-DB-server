<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2025 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Services;

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
use App\Entity\Parts\PartCustomState;
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
use App\Exceptions\EntityNotSupportedException;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum ElementTypes: string implements TranslatableInterface
{
    case ATTACHMENT = "attachment";
    case CATEGORY = "category";
    case ATTACHMENT_TYPE = "attachment_type";
    case PROJECT = "project";
    case PROJECT_BOM_ENTRY = "project_bom_entry";
    case FOOTPRINT = "footprint";
    case MANUFACTURER = "manufacturer";
    case MEASUREMENT_UNIT = "measurement_unit";
    case PART = "part";
    case PART_LOT = "part_lot";
    case STORAGE_LOCATION = "storage_location";
    case SUPPLIER = "supplier";
    case CURRENCY = "currency";
    case ORDERDETAIL = "orderdetail";
    case PRICEDETAIL = "pricedetail";
    case GROUP = "group";
    case USER = "user";
    case PARAMETER = "parameter";
    case LABEL_PROFILE = "label_profile";
    case PART_ASSOCIATION = "part_association";
    case BULK_INFO_PROVIDER_IMPORT_JOB = "bulk_info_provider_import_job";
    case BULK_INFO_PROVIDER_IMPORT_JOB_PART = "bulk_info_provider_import_job_part";
    case PART_CUSTOM_STATE = "part_custom_state";

    //Child classes has to become before parent classes
    private const CLASS_MAPPING = [
        Attachment::class => self::ATTACHMENT,
        Category::class => self::CATEGORY,
        AttachmentType::class => self::ATTACHMENT_TYPE,
        Project::class => self::PROJECT,
        ProjectBOMEntry::class => self::PROJECT_BOM_ENTRY,
        Footprint::class => self::FOOTPRINT,
        Manufacturer::class => self::MANUFACTURER,
        MeasurementUnit::class => self::MEASUREMENT_UNIT,
        Part::class => self::PART,
        PartLot::class => self::PART_LOT,
        StorageLocation::class => self::STORAGE_LOCATION,
        Supplier::class => self::SUPPLIER,
        Currency::class => self::CURRENCY,
        Orderdetail::class => self::ORDERDETAIL,
        Pricedetail::class => self::PRICEDETAIL,
        Group::class => self::GROUP,
        User::class => self::USER,
        AbstractParameter::class => self::PARAMETER,
        LabelProfile::class => self::LABEL_PROFILE,
        PartAssociation::class => self::PART_ASSOCIATION,
        BulkInfoProviderImportJob::class => self::BULK_INFO_PROVIDER_IMPORT_JOB,
        BulkInfoProviderImportJobPart::class => self::BULK_INFO_PROVIDER_IMPORT_JOB_PART,
        PartCustomState::class => self::PART_CUSTOM_STATE,
    ];

    /**
     * Gets the default translation key for the label of the element type (singular form).
     */
    public function getDefaultLabelKey(): string
    {
        return match ($this) {
            self::ATTACHMENT => 'attachment.label',
            self::CATEGORY => 'category.label',
            self::ATTACHMENT_TYPE => 'attachment_type.label',
            self::PROJECT => 'project.label',
            self::PROJECT_BOM_ENTRY => 'project_bom_entry.label',
            self::FOOTPRINT => 'footprint.label',
            self::MANUFACTURER => 'manufacturer.label',
            self::MEASUREMENT_UNIT => 'measurement_unit.label',
            self::PART => 'part.label',
            self::PART_LOT => 'part_lot.label',
            self::STORAGE_LOCATION => 'storelocation.label',
            self::SUPPLIER => 'supplier.label',
            self::CURRENCY => 'currency.label',
            self::ORDERDETAIL => 'orderdetail.label',
            self::PRICEDETAIL => 'pricedetail.label',
            self::GROUP => 'group.label',
            self::USER => 'user.label',
            self::PARAMETER => 'parameter.label',
            self::LABEL_PROFILE => 'label_profile.label',
            self::PART_ASSOCIATION => 'part_association.label',
            self::BULK_INFO_PROVIDER_IMPORT_JOB => 'bulk_info_provider_import_job.label',
            self::BULK_INFO_PROVIDER_IMPORT_JOB_PART => 'bulk_info_provider_import_job_part.label',
            self::PART_CUSTOM_STATE => 'part_custom_state.label',
        };
    }

    public function getDefaultPluralLabelKey(): string
    {
        return match ($this) {
            self::ATTACHMENT => 'attachment.labelp',
            self::CATEGORY => 'category.labelp',
            self::ATTACHMENT_TYPE => 'attachment_type.labelp',
            self::PROJECT => 'project.labelp',
            self::PROJECT_BOM_ENTRY => 'project_bom_entry.labelp',
            self::FOOTPRINT => 'footprint.labelp',
            self::MANUFACTURER => 'manufacturer.labelp',
            self::MEASUREMENT_UNIT => 'measurement_unit.labelp',
            self::PART => 'part.labelp',
            self::PART_LOT => 'part_lot.labelp',
            self::STORAGE_LOCATION => 'storelocation.labelp',
            self::SUPPLIER => 'supplier.labelp',
            self::CURRENCY => 'currency.labelp',
            self::ORDERDETAIL => 'orderdetail.labelp',
            self::PRICEDETAIL => 'pricedetail.labelp',
            self::GROUP => 'group.labelp',
            self::USER => 'user.labelp',
            self::PARAMETER => 'parameter.labelp',
            self::LABEL_PROFILE => 'label_profile.labelp',
            self::PART_ASSOCIATION => 'part_association.labelp',
            self::BULK_INFO_PROVIDER_IMPORT_JOB => 'bulk_info_provider_import_job.labelp',
            self::BULK_INFO_PROVIDER_IMPORT_JOB_PART => 'bulk_info_provider_import_job_part.labelp',
            self::PART_CUSTOM_STATE => 'part_custom_state.labelp',
        };
    }

    /**
     * Used to get a user-friendly representation of the object that can be translated.
     * For this the singular default label key is used.
     * @param  TranslatorInterface  $translator
     * @param  string|null  $locale
     * @return string
     */
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->getDefaultLabelKey(), locale: $locale);
    }

    /**
     * Determines the ElementType from a value, which can either be an enum value, an ElementTypes instance, a class name or an object instance.
     * @param  string|object  $value
     * @return self
     */
    public static function fromValue(string|object $value): self
    {
        if ($value instanceof self) {
            return $value;
        }
        if (is_object($value)) {
            return self::fromClass($value);
        }


        //Otherwise try to parse it as enum value first
        $enumValue = self::tryFrom($value);

        //Otherwise try to get it from class name
        return $enumValue ?? self::fromClass($value);
    }

    /**
     * Determines the ElementType from a class name or object instance.
     * @param  string|object  $class
     * @throws EntityNotSupportedException if the class is not supported
     * @return self
     */
    public static function fromClass(string|object $class): self
    {
        if (is_object($class)) {
            $className = get_class($class);
        } else {
            $className = $class;
        }

        if (array_key_exists($className, self::CLASS_MAPPING)) {
            return self::CLASS_MAPPING[$className];
        }

        //Otherwise we need to check for inheritance
        foreach (self::CLASS_MAPPING as $entityClass => $elementType) {
            if (is_a($className, $entityClass, true)) {
                return $elementType;
            }
        }

        throw new EntityNotSupportedException(sprintf('No localized label for the element with type %s was found!', $className));
    }
}
