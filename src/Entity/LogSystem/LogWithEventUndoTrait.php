<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Entity\LogSystem;

use App\Entity\Contracts\LogWithEventUndoInterface;
use App\Services\LogSystem\EventUndoMode;

trait LogWithEventUndoTrait
{
    public function isUndoEvent(): bool
    {
        return isset($this->extra['u']);
    }

    public function getUndoEventID(): ?int
    {
        return $this->extra['u'] ?? null;
    }

    public function setUndoneEvent(AbstractLogEntry $event, EventUndoMode $mode = EventUndoMode::UNDO): LogWithEventUndoInterface
    {
        $this->extra['u'] = $event->getID();
        $this->extra['um'] = $mode->toExtraInt();

        return $this;
    }

    public function getUndoMode(): EventUndoMode
    {
        $mode_int = $this->extra['um'] ?? 1;
        return EventUndoMode::fromExtraInt($mode_int);
    }
}