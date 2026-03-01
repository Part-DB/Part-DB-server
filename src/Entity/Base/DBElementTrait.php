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

namespace App\Entity\Base;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Trait providing basic database element functionality with an ID.
 */
trait DBElementTrait
{
    /**
     * @var int|null The Identification number for this element. This value is unique for the element in this table.
     * Null if the element is not saved to DB yet.
     */
    #[Groups(['full', 'api:basic:read'])]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    protected ?int $id = null;

    /**
     * Get the ID. The ID can be zero, or even negative (for virtual elements). If an element is virtual, can be
     * checked with isVirtualElement().
     *
     * Returns null, if the element is not saved to the DB yet.
     *
     * @return int|null the ID of this element
     */
    public function getID(): ?int
    {
        return $this->id;
    }

    /**
     * Clone helper for DB element - resets ID on clone.
     */
    protected function cloneDBElement(): void
    {
        if ($this->id) {
            //Set ID to null, so that a new entry is created
            $this->id = null;
        }
    }
}
