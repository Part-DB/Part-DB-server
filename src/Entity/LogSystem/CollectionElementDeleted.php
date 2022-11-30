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

namespace App\Entity\LogSystem;

use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\AttachmentType;
use App\Entity\Attachments\AttachmentTypeAttachment;
use App\Entity\Attachments\CategoryAttachment;
use App\Entity\Attachments\CurrencyAttachment;
use App\Entity\Attachments\DeviceAttachment;
use App\Entity\Attachments\FootprintAttachment;
use App\Entity\Attachments\GroupAttachment;
use App\Entity\Attachments\ManufacturerAttachment;
use App\Entity\Attachments\MeasurementUnitAttachment;
use App\Entity\Attachments\PartAttachment;
use App\Entity\Attachments\StorelocationAttachment;
use App\Entity\Attachments\SupplierAttachment;
use App\Entity\Attachments\UserAttachment;
use App\Entity\Base\AbstractDBElement;
use App\Entity\Contracts\LogWithEventUndoInterface;
use App\Entity\Contracts\NamedElementInterface;
use App\Entity\Devices\Device;
use App\Entity\Parameters\AbstractParameter;
use App\Entity\Parameters\AttachmentTypeParameter;
use App\Entity\Parameters\CategoryParameter;
use App\Entity\Parameters\CurrencyParameter;
use App\Entity\Parameters\DeviceParameter;
use App\Entity\Parameters\FootprintParameter;
use App\Entity\Parameters\GroupParameter;
use App\Entity\Parameters\ManufacturerParameter;
use App\Entity\Parameters\MeasurementUnitParameter;
use App\Entity\Parameters\PartParameter;
use App\Entity\Parameters\StorelocationParameter;
use App\Entity\Parameters\SupplierParameter;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Part;
use App\Entity\Parts\Storelocation;
use App\Entity\Parts\Supplier;
use App\Entity\PriceInformations\Currency;
use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\User;
use App\Repository\Parts\ManufacturerRepository;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;

/**
 * @ORM\Entity()
 * This log entry is created when an element is deleted, that is used in a collection of an other entity.
 * This is needed to signal time travel, that it has to undelete the deleted entity.
 */
class CollectionElementDeleted extends AbstractLogEntry implements LogWithEventUndoInterface
{
    protected string $typeString = 'collection_element_deleted';
    protected int $level = self::LEVEL_INFO;

    public function __construct(AbstractDBElement $changed_element, string $collection_name, AbstractDBElement $deletedElement)
    {
        parent::__construct();

        $this->level = self::LEVEL_INFO;
        $this->setTargetElement($changed_element);
        $this->extra['n'] = $collection_name;
        $this->extra['c'] = self::targetTypeClassToID(get_class($deletedElement));
        $this->extra['i'] = $deletedElement->getID();
        if ($deletedElement instanceof NamedElementInterface) {
            $this->extra['o'] = $deletedElement->getName();
        }
    }

    /**
     * Get the name of the collection (on target element) that was changed.
     */
    public function getCollectionName(): string
    {
        return $this->extra['n'];
    }

    /**
     * Gets the name of the element that was deleted.
     * Return null, if the element did not have a name.
     */
    public function getOldName(): ?string
    {
        return $this->extra['o'] ?? null;
    }

    /**
     * Returns the class of the deleted element.
     */
    public function getDeletedElementClass(): string
    {
        //The class name of our target element
        $tmp = self::targetTypeIdToClass($this->extra['c']);

        $reflection_class = new \ReflectionClass($tmp);
        //If the class is abstract, we have to map it to an instantiable class
        if ($reflection_class->isAbstract()) {
            return $this->resolveAbstractClassToInstantiableClass($tmp);
        }

        return $tmp;
    }

    /**
     * This functions maps an abstract class name derived from the extra c element to an instantiable class name (based on the target element of this log entry).
     * For example if the target element is a part and the extra c element is "App\Entity\Attachments\Attachment", this function will return "App\Entity\Attachments\PartAttachment".
     * @param  string  $abstract_class
     * @return string
     */
    private function resolveAbstractClassToInstantiableClass(string $abstract_class): string
    {
        if (is_a($abstract_class, AbstractParameter::class, true)) {
            switch ($this->getTargetClass()) {
                case AttachmentType::class:
                    return AttachmentTypeParameter::class;
                case Category::class:
                    return CategoryParameter::class;
                case Currency::class:
                    return CurrencyParameter::class;
                case Device::class:
                    return DeviceParameter::class;
                case Footprint::class:
                    return FootprintParameter::class;
                case Group::class:
                    return GroupParameter::class;
                case Manufacturer::class:
                    return ManufacturerParameter::class;
                case MeasurementUnit::class:
                    return MeasurementUnitParameter::class;
                case Part::class:
                    return PartParameter::class;
                case Storelocation::class:
                    return StorelocationParameter::class;
                case Supplier::class:
                    return SupplierParameter::class;

                default:
                    throw new \RuntimeException('Unknown target class for parameter: '.$this->getTargetClass());
            }
        }

        if (is_a($abstract_class, Attachment::class, true)) {
            switch ($this->getTargetClass()) {
                case AttachmentType::class:
                    return AttachmentTypeAttachment::class;
                case Category::class:
                    return CategoryAttachment::class;
                case Currency::class:
                    return CurrencyAttachment::class;
                case Device::class:
                    return DeviceAttachment::class;
                case Footprint::class:
                    return FootprintAttachment::class;
                case Group::class:
                    return GroupAttachment::class;
                case Manufacturer::class:
                    return ManufacturerAttachment::class;
                case MeasurementUnit::class:
                    return MeasurementUnitAttachment::class;
                case Part::class:
                    return PartAttachment::class;
                case Storelocation::class:
                    return StorelocationAttachment::class;
                case Supplier::class:
                    return SupplierAttachment::class;
                case User::class:
                    return UserAttachment::class;

                default:
                    throw new \RuntimeException('Unknown target class for parameter: '.$this->getTargetClass());
            }
        }

        throw new \RuntimeException('The class '.$abstract_class.' is abstract and no explicit resolving to an concrete type is defined!');
    }

    /**
     * Returns the ID of the deleted element.
     */
    public function getDeletedElementID(): int
    {
        return $this->extra['i'];
    }

    public function isUndoEvent(): bool
    {
        return isset($this->extra['u']);
    }

    public function getUndoEventID(): ?int
    {
        return $this->extra['u'] ?? null;
    }

    public function setUndoneEvent(AbstractLogEntry $event, string $mode = 'undo'): LogWithEventUndoInterface
    {
        $this->extra['u'] = $event->getID();

        if ('undo' === $mode) {
            $this->extra['um'] = 1;
        } elseif ('revert' === $mode) {
            $this->extra['um'] = 2;
        } else {
            throw new InvalidArgumentException('Passed invalid $mode!');
        }

        return $this;
    }

    public function getUndoMode(): string
    {
        $mode_int = $this->extra['um'] ?? 1;
        if (1 === $mode_int) {
            return 'undo';
        }

        return 'revert';
    }
}
