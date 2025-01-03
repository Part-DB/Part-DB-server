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

namespace App\Services\LogSystem;

use App\Entity\LogSystem\AbstractLogEntry;

class EventUndoHelper
{
    protected ?AbstractLogEntry $undone_event = null;
    protected EventUndoMode $mode = EventUndoMode::UNDO;

    public function __construct()
    {
    }

    public function setMode(EventUndoMode $mode): void
    {
        $this->mode = $mode;
    }

    public function getMode(): EventUndoMode
    {
        return $this->mode;
    }

    /**
     * Set which event log is currently undone.
     * After the flush this message is cleared.
     */
    public function setUndoneEvent(?AbstractLogEntry $undone_event): void
    {
        $this->undone_event = $undone_event;
    }

    /**
     * Returns event that is currently undone.
     */
    public function getUndoneEvent(): ?AbstractLogEntry
    {
        return $this->undone_event;
    }

    /**
     * Clear the currently set undone event.
     */
    public function clearUndoneEvent(): void
    {
        $this->undone_event = null;
    }

    /**
     * Check if an event is undone.
     */
    public function isUndo(): bool
    {
        return $this->undone_event instanceof AbstractLogEntry;
    }
}
