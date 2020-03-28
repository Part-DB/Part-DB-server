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

namespace App\Entity\Parameters;

use App\Entity\PriceInformations\Currency;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * A attachment attached to a category element.
 *
 * @ORM\Entity()
 * @UniqueEntity(fields={"name", "group", "element"})
 */
class CurrencyParameter extends AbstractParameter
{
    public const ALLOWED_ELEMENT_CLASS = Currency::class;

    /**
     * @var Currency the element this para is associated with
     * @ORM\ManyToOne(targetEntity="App\Entity\PriceInformations\Currency", inversedBy="parameters")
     * @ORM\JoinColumn(name="element_id", referencedColumnName="id", nullable=false, onDelete="CASCADE").
     */
    protected $element;
}
