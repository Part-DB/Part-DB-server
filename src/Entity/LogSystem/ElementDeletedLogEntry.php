<?php
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

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Entity\LogSystem;

use App\Entity\Base\AbstractDBElement;
use App\Entity\Contracts\LogWithCommentInterface;
use App\Entity\Contracts\LogWithEventUndoInterface;
use App\Entity\Contracts\NamedElementInterface;
use App\Entity\Contracts\TimeTravelInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class ElementDeletedLogEntry extends AbstractLogEntry implements TimeTravelInterface, LogWithCommentInterface, LogWithEventUndoInterface
{
    protected $typeString = 'element_deleted';

    public function __construct(AbstractDBElement $deleted_element)
    {
        parent::__construct();
        $this->level = self::LEVEL_INFO;
        $this->setTargetElement($deleted_element);
    }

    /**
     * @inheritDoc
     * @return $this
     */
    public function setTargetElement(?AbstractDBElement $element): AbstractLogEntry
    {
        parent::setTargetElement($element);
        if ($element instanceof NamedElementInterface) {
            $this->setOldName($element->getName());
        }
        return $this;
    }

    public function setOldName(string $old_name): self
    {
        $this->extra['n'] = $old_name;
        return $this;
    }

    public function getOldName(): ?string
    {
        return $this->extra['n'] ?? null;
    }

    /**
     * Sets the old data for this entry.
     * @param array $old_data
     * @return $this
     */
    public function setOldData(array $old_data): self
    {
        $this->extra['o'] = $old_data;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function hasOldDataInformations(): bool
    {
        return !empty($this->extra['o']);
    }

    /**
     * @inheritDoc
     */
    public function getOldData(): array
    {
        return $this->extra['o'] ?? [];
    }

    /**
     * @inheritDoc
     */
    public function hasComment(): bool
    {
        return isset($this->extra['m']);
    }

    /**
     * @inheritDoc
     */
    public function getComment(): ?string
    {
        return $this->extra['m'] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function setComment(?string $new_comment): LogWithCommentInterface
    {
        $this->extra['m'] = $new_comment;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function isUndoEvent(): bool
    {
        return isset($this->extra['u']);
    }

    /**
     * @inheritDoc
     */
    public function getUndoEventID(): ?int
    {
        return $this->extra['u'] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function setUndoneEvent(AbstractLogEntry $event, string $mode = 'undo'): LogWithEventUndoInterface
    {
        $this->extra['u'] = $event->getID();

        if ($mode === 'undo') {
            $this->extra['um'] = 1;
        } elseif ($mode === 'revert') {
            $this->extra['um'] = 2;
        } else {
            throw new \InvalidArgumentException('Passed invalid $mode!');
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getUndoMode(): string
    {
        $mode_int = $this->extra['um'] ?? 1;
        if ($mode_int === 1) {
            return 'undo';
        }
        return 'revert';
    }
}
