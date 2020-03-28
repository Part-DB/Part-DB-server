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

namespace App\Entity\Contracts;

use App\Entity\LogSystem\AbstractLogEntry;

interface LogWithEventUndoInterface
{
    /**
     * Checks if this element undoes another event.
     *
     * @return bool
     */
    public function isUndoEvent(): bool;

    /**
     * Returns the ID of the undone event or null if no event is undone.
     *
     * @return int|null
     */
    public function getUndoEventID(): ?int;

    /**
     * Sets the event that is undone, and the undo mode.
     *
     * @return $this
     */
    public function setUndoneEvent(AbstractLogEntry $event, string $mode = 'undo'): self;

    /**
     * Returns the mode how the event was undone:
     * "undo" = Only a single event was applied to element
     * "revert" = Element was reverted to the state it was to the timestamp of the log.
     *
     * @return string
     */
    public function getUndoMode(): string;
}
