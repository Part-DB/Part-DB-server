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


namespace App\Entity\Parts\PartTraits;

use App\Entity\EDA\EDAPartInfo;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Embedded;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\Valid;

trait EDATrait
{
    #[Valid]
    #[Embedded(class: EDAPartInfo::class)]
    #[Groups(['full', 'part:read', 'part:write'])]
    protected EDAPartInfo $eda_info;

    public function getEdaInfo(): EDAPartInfo
    {
        return $this->eda_info;
    }

    public function setEdaInfo(?EDAPartInfo $eda_info): self
    {
        if ($eda_info !== null) {
            //Do a clone, to ensure that the property is updated in the database
            $eda_info = clone $eda_info;
        }

        $this->eda_info = $eda_info;
        return $this;
    }
}