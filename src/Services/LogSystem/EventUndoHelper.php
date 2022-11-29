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
use InvalidArgumentException;

class EventUndoHelper
{
    public const MODE_UNDO = 'undo';
    public const MODE_REVERT = 'revert';

    protected const ALLOWED_MODES = [self::MODE_REVERT, self::MODE_UNDO];

    protected ?AbstractLogEntry $undone_event;
    protected string $mode;

    public function __construct()
    {
        $this->undone_event = null;
        $this->mode = self::MODE_UNDO;
    }

    public function setMode(string $mode): void
    {
        if (!in_array($mode, self::ALLOWED_MODES, true)) {
            throw new InvalidArgumentException('Invalid mode passed!');
        }
        $this->mode = $mode;
    }

    public function getMode(): string
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
     * Clear the currently the set undone event.
     */
    public function clearUndoneEvent(): void
    {
        $this->undone_event = null;
    }

    /**
     * Check if a event is undone.
     */
    public function isUndo(): bool
    {
        return $this->undone_event instanceof AbstractLogEntry;
    }
}
