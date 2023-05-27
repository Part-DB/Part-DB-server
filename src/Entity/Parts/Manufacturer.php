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

namespace App\Entity\Parts;

use App\Entity\Attachments\ManufacturerAttachment;
use App\Entity\Base\AbstractCompany;
use App\Entity\Parameters\ManufacturerParameter;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Manufacturer.
 *
 * @ORM\Entity(repositoryClass="App\Repository\Parts\ManufacturerRepository")
 * @ORM\Table("`manufacturers`", indexes={
 *     @ORM\Index(name="manufacturer_name", columns={"name"}),
 *     @ORM\Index(name="manufacturer_idx_parent_name", columns={"parent_id", "name"}),
 * })
 */
class Manufacturer extends AbstractCompany
{
    /**
     * @ORM\ManyToOne(targetEntity="Manufacturer", inversedBy="children")
     * @ORM\JoinColumn(name="parent_id", referencedColumnName="id")
     */
    protected ?\App\Entity\Base\AbstractStructuralDBElement $parent;

    /**
     * @ORM\OneToMany(targetEntity="Manufacturer", mappedBy="parent")
     * @ORM\OrderBy({"name" = "ASC"})
     * @var Collection
     */
    protected Collection $children;

    /**
     * @var Collection<int, ManufacturerAttachment>
     * @ORM\OneToMany(targetEntity="App\Entity\Attachments\ManufacturerAttachment", mappedBy="element", cascade={"persist", "remove"}, orphanRemoval=true)
     * @ORM\OrderBy({"name" = "ASC"})
     */
    #[Assert\Valid]
    protected Collection $attachments;

    /** @var Collection<int, ManufacturerParameter>
     * @ORM\OneToMany(targetEntity="App\Entity\Parameters\ManufacturerParameter", mappedBy="element", cascade={"persist", "remove"}, orphanRemoval=true)
     * @ORM\OrderBy({"group" = "ASC" ,"name" = "ASC"})
     */
    #[Assert\Valid]
    protected Collection $parameters;
}
