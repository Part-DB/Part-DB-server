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

use App\Repository\NamedDBElementRepository;
use App\Entity\Contracts\NamedElementInterface;
use App\Entity\Contracts\TimeStampableInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * All subclasses of this class have an attribute "name".
 */
#[ORM\MappedSuperclass(repositoryClass: NamedDBElementRepository::class)]
#[ORM\HasLifecycleCallbacks]
abstract class AbstractNamedDBElement extends AbstractDBElement implements NamedElementInterface, TimeStampableInterface, \Stringable
{
    use TimestampTrait;
    use NamedElementTrait;

    public function __clone()
    {
        if ($this->id) {
            //We create a new object, so give it a new creation date
            $this->addedDate = null;
        }
        parent::__clone();
    }
}
