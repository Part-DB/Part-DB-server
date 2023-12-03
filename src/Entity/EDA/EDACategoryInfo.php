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

#[Embeddable]
class EDACategoryInfo
{
    /**
     * @var string|null The reference prefix of the Part in the schematic. E.g. "R" for resistors, or "C" for capacitors.
     */
    #[Column(type: Types::STRING, nullable: true)]
    #[Groups(['full', 'category:read', 'category:write'])]
    private ?string $reference_prefix = null;

    /** @var bool|null Visibility of this part to EDA software in trinary logic. True=Visible, False=Invisible, Null=Auto */
    #[Column(name: 'invisible', type: Types::BOOLEAN, nullable: true)] //TODO: Rename column to visibility
    #[Groups(['full', 'category:read', 'category:write'])]
    private ?bool $visibility = null;

    /** @var bool|null If this is set to true, then this part will be excluded from the BOM */
    #[Column(type: Types::BOOLEAN, nullable: true)]
    #[Groups(['full', 'category:read', 'category:write'])]
    private ?bool $exclude_from_bom = null;

    /** @var bool|null If this is set to true, then this part will be excluded from the board/the PCB */
    #[Column(type: Types::BOOLEAN, nullable: true)]
    #[Groups(['full', 'category:read', 'category:write'])]
    private ?bool $exclude_from_board = null;

    /** @var bool|null If this is set to true, then this part will be excluded in the simulation */
    #[Column(type: Types::BOOLEAN, nullable: true)]
    #[Groups(['full', 'category:read', 'category:write'])]
    private ?bool $exclude_from_sim = true;

    /** @var string|null The KiCAD schematic symbol, which should be used (the path to the library) */
    #[Column(type: Types::STRING, nullable: true)]
    #[Groups(['full', 'category:read', 'category:write'])]
    private ?string $kicad_symbol = null;

    public function getReferencePrefix(): ?string
    {
        return $this->reference_prefix;
    }

    public function setReferencePrefix(?string $reference_prefix): EDACategoryInfo
    {
        $this->reference_prefix = $reference_prefix;
        return $this;
    }

    public function getVisibility(): ?bool
    {
        return $this->visibility;
    }

    public function setVisibility(?bool $visibility): EDACategoryInfo
    {
        $this->visibility = $visibility;
        return $this;
    }

    public function getExcludeFromBom(): ?bool
    {
        return $this->exclude_from_bom;
    }

    public function setExcludeFromBom(?bool $exclude_from_bom): EDACategoryInfo
    {
        $this->exclude_from_bom = $exclude_from_bom;
        return $this;
    }

    public function getExcludeFromBoard(): ?bool
    {
        return $this->exclude_from_board;
    }

    public function setExcludeFromBoard(?bool $exclude_from_board): EDACategoryInfo
    {
        $this->exclude_from_board = $exclude_from_board;
        return $this;
    }

    public function getExcludeFromSim(): ?bool
    {
        return $this->exclude_from_sim;
    }

    public function setExcludeFromSim(?bool $exclude_from_sim): EDACategoryInfo
    {
        $this->exclude_from_sim = $exclude_from_sim;
        return $this;
    }

    public function getKicadSymbol(): ?string
    {
        return $this->kicad_symbol;
    }

    public function setKicadSymbol(?string $kicad_symbol): EDACategoryInfo
    {
        $this->kicad_symbol = $kicad_symbol;
        return $this;
    }

}