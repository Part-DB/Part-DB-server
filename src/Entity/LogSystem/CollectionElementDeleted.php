<?php

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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

use App\Entity\Base\AbstractDBElement;
use App\Entity\Contracts\LogWithEventUndoInterface;
use App\Entity\Contracts\NamedElementInterface;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;

/**
 * @ORM\Entity()
 * This log entry is created when an element is deleted, that is used in a collection of an other entity.
 * This is needed to signal time travel, that it has to undelete the deleted entity.
 */
class CollectionElementDeleted extends AbstractLogEntry implements LogWithEventUndoInterface
{
    protected $typeString = 'collection_element_deleted';
    protected $level = self::LEVEL_INFO;

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
        return self::targetTypeIdToClass($this->extra['c']);
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
