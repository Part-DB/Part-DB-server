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

namespace App\Entity\LogSystem;

use App\Entity\Base\AbstractDBElement;
use App\Entity\Contracts\LogWithCommentInterface;
use App\Entity\Contracts\LogWithEventUndoInterface;
use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\User;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;

/**
 * @ORM\Entity()
 */
class ElementCreatedLogEntry extends AbstractLogEntry implements LogWithCommentInterface, LogWithEventUndoInterface
{
    protected string $typeString = 'element_created';

    public function __construct(AbstractDBElement $new_element)
    {
        parent::__construct();
        $this->level = self::LEVEL_INFO;
        $this->setTargetElement($new_element);

        //Creation of new users is maybe more interesting...
        if ($new_element instanceof User || $new_element instanceof Group) {
            $this->level = self::LEVEL_NOTICE;
        }
    }

    /**
     * Gets the instock when the part was created.
     */
    public function getCreationInstockValue(): ?string
    {
        return isset($this->extra['i']) ? (string)$this->extra['i'] : null;
    }

    /**
     * Checks if a creation instock value was saved with this entry.
     */
    public function hasCreationInstockValue(): bool
    {
        return null !== $this->getCreationInstockValue();
    }

    public function hasComment(): bool
    {
        return isset($this->extra['m']);
    }

    public function getComment(): ?string
    {
        return $this->extra['m'] ?? null;
    }

    public function setComment(?string $new_comment): LogWithCommentInterface
    {
        $this->extra['m'] = $new_comment;

        return $this;
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
