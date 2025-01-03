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
use App\Entity\Contracts\NamedElementInterface;
use App\Entity\Contracts\TimeTravelInterface;
use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class ElementDeletedLogEntry extends AbstractLogEntry implements TimeTravelInterface, LogWithCommentInterface, LogWithEventUndoInterface
{
    protected string $typeString = 'element_deleted';

    use LogWithEventUndoTrait;

    public function __construct(AbstractDBElement $deleted_element)
    {
        parent::__construct();
        $this->level = LogLevel::INFO;
        $this->setTargetElement($deleted_element);

        //Deletion of a user is maybe more interesting...
        if ($deleted_element instanceof User || $deleted_element instanceof Group) {
            $this->level = LogLevel::NOTICE;
        }
    }

    /**
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
     *
     * @return $this
     */
    public function setOldData(array $old_data): self
    {
        $this->extra['o'] = $old_data;

        return $this;
    }

    public function hasOldDataInformation(): bool
    {
        return !empty($this->extra['o']);
    }

    public function getOldData(): array
    {
        return $this->extra['o'] ?? [];
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
}
