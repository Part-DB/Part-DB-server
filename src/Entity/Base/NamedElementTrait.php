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

namespace App\Entity\Base;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Trait providing named element functionality.
 */
trait NamedElementTrait
{
    /**
     * @var string The name of this element
     */
    #[Assert\NotBlank]
    #[Groups(['simple', 'extended', 'full', 'import', 'api:basic:read', 'api:basic:write'])]
    #[ORM\Column(type: Types::STRING)]
    #[Assert\Length(max: 255)]
    protected string $name = '';

    /**
     * Get the name of this element.
     *
     * @return string the name of this element
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Change the name of this element.
     *
     * @param string $new_name the new name
     */
    public function setName(string $new_name): self
    {
        $this->name = $new_name;

        return $this;
    }

    /**
     * String representation returns the name.
     */
    public function __toString(): string
    {
        return $this->getName();
    }
}
