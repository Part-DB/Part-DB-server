<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Helpers\Trees;

class TreeViewNodeState
{
    /** @var bool|null */
    protected $checked = null;

    /** @var bool|null */
    protected $disabled = null;

    /** @var bool|null */
    protected $expanded = null;

    /** @var bool|null */
    protected $selected = null;

    /**
     * @return bool|null
     */
    public function getDisabled(): ?bool
    {
        return $this->disabled;
    }

    public function setDisabled(?bool $disabled): void
    {
        $this->disabled = $disabled;
    }

    /**
     * @return bool|null
     */
    public function getExpanded(): ?bool
    {
        return $this->expanded;
    }

    public function setExpanded(?bool $expanded): void
    {
        $this->expanded = $expanded;
    }

    /**
     * @return bool|null
     */
    public function getSelected(): ?bool
    {
        return $this->selected;
    }

    public function setSelected(?bool $selected): void
    {
        $this->selected = $selected;
    }
}
