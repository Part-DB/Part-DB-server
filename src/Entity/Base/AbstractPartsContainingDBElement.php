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

use App\Entity\Attachments\Attachment;
use App\Entity\Parameters\AbstractParameter;
use App\Repository\AbstractPartsContainingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @template AT of Attachment
 * @template PT of AbstractParameter
 * @extends AbstractStructuralDBElement<AT, PT>
 */
#[ORM\MappedSuperclass(repositoryClass: AbstractPartsContainingRepository::class)]
abstract class AbstractPartsContainingDBElement extends AbstractStructuralDBElement
{
    #[Groups(['full', 'import'])]
    protected Collection $parameters;

    public function __construct()
    {
        parent::__construct();
        $this->parameters = new ArrayCollection();
    }
}
