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

declare(strict_types=1);


namespace App\Entity\EDA;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Embeddable;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\Length;

#[Embeddable]
class EDAFootprintInfo
{
    /** @var string|null The KiCAD footprint, which should be used (the path to the library) */
    #[Column(type: Types::STRING, nullable: true)]
    #[Groups(['full', 'footprint:read', 'footprint:write', 'import'])]
    #[Length(max: 255)]
    private ?string $kicad_footprint = null;

    public function getKicadFootprint(): ?string
    {
        return $this->kicad_footprint;
    }

    public function setKicadFootprint(?string $kicad_footprint): EDAFootprintInfo
    {
        $this->kicad_footprint = $kicad_footprint;
        return $this;
    }
}