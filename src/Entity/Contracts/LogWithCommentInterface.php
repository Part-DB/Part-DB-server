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

interface LogWithCommentInterface
{
    /**
     * Checks if this log entry has a user provided comment.
     */
    public function hasComment(): bool;

    /**
     * Gets the user provided comment associated with this log entry.
     * Returns null if not comment was set.
     */
    public function getComment(): ?string;

    /**
     * Sets the user provided comment associated with this log entry.
     *
     * @return $this
     */
    public function setComment(?string $new_comment): self;
}
