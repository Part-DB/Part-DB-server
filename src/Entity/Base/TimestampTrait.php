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

namespace App\Entity\Base;

use DateTime;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * A entity with these trait contains informations about, when it was created and edited last time.
 */
trait TimestampTrait
{
    /**
     * @var DateTime the date when this element was modified the last time
     * @ORM\Column(type="datetime", name="last_modified", options={"default"="CURRENT_TIMESTAMP"})
     * @Groups({"extended", "full"})
     */
    protected $lastModified;

    /**
     * @var DateTime the date when this element was created
     * @ORM\Column(type="datetime", name="datetime_added", options={"default"="CURRENT_TIMESTAMP"})
     * @Groups({"extended", "full"})
     */
    protected $addedDate;

    /**
     * Returns the last time when the element was modified.
     * Returns null if the element was not yet saved to DB yet.
     *
     * @return DateTime|null the time of the last edit
     */
    public function getLastModified(): ?DateTime
    {
        return $this->lastModified;
    }

    /**
     * Returns the date/time when the element was created.
     * Returns null if the element was not yet saved to DB yet.
     *
     * @return DateTime|null the creation time of the part
     */
    public function getAddedDate(): ?DateTime
    {
        return $this->addedDate;
    }

    /**
     * Helper for updating the timestamp. It is automatically called by doctrine before persisting.
     *
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function updateTimestamps(): void
    {
        $this->lastModified = new DateTime('now');
        if (null === $this->addedDate) {
            $this->addedDate = new DateTime('now');
        }
    }
}
