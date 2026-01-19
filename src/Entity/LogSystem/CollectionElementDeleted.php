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

use App\Entity\AssemblySystem\Assembly;
use App\Entity\Attachments\AssemblyAttachment;
use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\AttachmentType;
use App\Entity\Attachments\AttachmentTypeAttachment;
use App\Entity\Attachments\CategoryAttachment;
use App\Entity\Attachments\CurrencyAttachment;
use App\Entity\Attachments\PartCustomStateAttachment;
use App\Entity\Attachments\ProjectAttachment;
use App\Entity\Attachments\FootprintAttachment;
use App\Entity\Attachments\GroupAttachment;
use App\Entity\Attachments\ManufacturerAttachment;
use App\Entity\Attachments\MeasurementUnitAttachment;
use App\Entity\Attachments\PartAttachment;
use App\Entity\Attachments\StorageLocationAttachment;
use App\Entity\Attachments\SupplierAttachment;
use App\Entity\Attachments\UserAttachment;
use App\Entity\Base\AbstractDBElement;
use App\Entity\Contracts\LogWithEventUndoInterface;
use App\Entity\Contracts\NamedElementInterface;
use App\Entity\Parameters\PartCustomStateParameter;
use App\Entity\Parts\PartCustomState;
use App\Entity\Parameters\AssemblyParameter;
use App\Entity\ProjectSystem\Project;
use App\Entity\Parameters\AbstractParameter;
use App\Entity\Parameters\AttachmentTypeParameter;
use App\Entity\Parameters\CategoryParameter;
use App\Entity\Parameters\CurrencyParameter;
use App\Entity\Parameters\ProjectParameter;
use App\Entity\Parameters\FootprintParameter;
use App\Entity\Parameters\GroupParameter;
use App\Entity\Parameters\ManufacturerParameter;
use App\Entity\Parameters\MeasurementUnitParameter;
use App\Entity\Parameters\PartParameter;
use App\Entity\Parameters\StorageLocationParameter;
use App\Entity\Parameters\SupplierParameter;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Part;
use App\Entity\Parts\StorageLocation;
use App\Entity\Parts\Supplier;
use App\Entity\PriceInformations\Currency;
use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class CollectionElementDeleted extends AbstractLogEntry implements LogWithEventUndoInterface
{
    use LogWithEventUndoTrait;

    protected string $typeString = 'collection_element_deleted';

    public function __construct(AbstractDBElement $changed_element, string $collection_name, AbstractDBElement $deletedElement)
    {
        parent::__construct();

        $this->level = LogLevel::INFO;

        $this->setTargetElement($changed_element);
        $this->extra['n'] = $collection_name;
        $this->extra['c'] = LogTargetType::fromElementClass($deletedElement)->value;
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
        $tmp = LogTargetType::from($this->extra['c'])->toClass();

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
     */
    private function resolveAbstractClassToInstantiableClass(string $abstract_class): string
    {
        if (is_a($abstract_class, AbstractParameter::class, true)) {
            return match ($this->getTargetClass()) {
                Assembly::class => AssemblyParameter::class,
                AttachmentType::class => AttachmentTypeParameter::class,
                Category::class => CategoryParameter::class,
                Currency::class => CurrencyParameter::class,
                Project::class => ProjectParameter::class,
                Footprint::class => FootprintParameter::class,
                Group::class => GroupParameter::class,
                Manufacturer::class => ManufacturerParameter::class,
                MeasurementUnit::class => MeasurementUnitParameter::class,
                Part::class => PartParameter::class,
                StorageLocation::class => StorageLocationParameter::class,
                Supplier::class => SupplierParameter::class,
                PartCustomState::class => PartCustomStateParameter::class,
                default => throw new \RuntimeException('Unknown target class for parameter: '.$this->getTargetClass()),
            };
        }

        if (is_a($abstract_class, Attachment::class, true)) {
            return match ($this->getTargetClass()) {
                AttachmentType::class => AttachmentTypeAttachment::class,
                Category::class => CategoryAttachment::class,
                Currency::class => CurrencyAttachment::class,
                Project::class => ProjectAttachment::class,
                Assembly::class => AssemblyAttachment::class,
                Footprint::class => FootprintAttachment::class,
                Group::class => GroupAttachment::class,
                Manufacturer::class => ManufacturerAttachment::class,
                MeasurementUnit::class => MeasurementUnitAttachment::class,
                Part::class => PartAttachment::class,
                PartCustomState::class => PartCustomStateAttachment::class,
                StorageLocation::class => StorageLocationAttachment::class,
                Supplier::class => SupplierAttachment::class,
                User::class => UserAttachment::class,
                default => throw new \RuntimeException('Unknown target class for parameter: '.$this->getTargetClass()),
            };
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
}
