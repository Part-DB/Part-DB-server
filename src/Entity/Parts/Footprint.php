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

use App\Repository\Parts\FootprintRepository;
use App\Entity\Base\AbstractStructuralDBElement;
use Doctrine\Common\Collections\ArrayCollection;
use App\Entity\Attachments\FootprintAttachment;
use App\Entity\Base\AbstractPartsContainingDBElement;
use App\Entity\Parameters\FootprintParameter;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity represents a footprint of a part (its physical dimensions and shape).
 *
 * @extends AbstractPartsContainingDBElement<FootprintAttachment, FootprintParameter>
 */
#[ORM\Entity(repositoryClass: FootprintRepository::class)]
#[ORM\Table('`footprints`')]
#[ORM\Index(name: 'footprint_idx_name', columns: ['name'])]
#[ORM\Index(name: 'footprint_idx_parent_name', columns: ['parent_id', 'name'])]
class Footprint extends AbstractPartsContainingDBElement
{
    #[ORM\ManyToOne(targetEntity: 'Footprint', inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id')]
    protected ?AbstractStructuralDBElement $parent = null;

    #[ORM\OneToMany(targetEntity: 'Footprint', mappedBy: 'parent')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    protected Collection $children;

    /**
     * @var Collection<int, FootprintAttachment>
     */
    #[Assert\Valid]
    #[ORM\OneToMany(targetEntity: FootprintAttachment::class, mappedBy: 'element', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['name' => 'ASC'])]
    protected Collection $attachments;

    /**
     * @var FootprintAttachment|null
     */
    #[ORM\ManyToOne(targetEntity: FootprintAttachment::class)]
    #[ORM\JoinColumn(name: 'id_footprint_3d')]
    protected ?FootprintAttachment $footprint_3d = null;

    /** @var Collection<int, FootprintParameter>
     */
    #[Assert\Valid]
    #[ORM\OneToMany(targetEntity: FootprintParameter::class, mappedBy: 'element', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['group' => 'ASC', 'name' => 'ASC'])]
    protected Collection $parameters;

    /****************************************
     * Getters
     ****************************************/

    /**
     * Returns the 3D Model associated with this footprint.
     */
    public function getFootprint3d(): ?FootprintAttachment
    {
        return $this->footprint_3d;
    }

    /********************************************************************************
     *
     *   Setters
     *
     *********************************************************************************/
    /**
     * Sets the 3D Model associated with this footprint.
     *
     * @param FootprintAttachment|null $new_attachment The new 3D Model
     */
    public function setFootprint3d(?FootprintAttachment $new_attachment): self
    {
        $this->footprint_3d = $new_attachment;

        return $this;
    }
    public function __construct()
    {
        parent::__construct();
        $this->children = new ArrayCollection();
        $this->attachments = new ArrayCollection();
        $this->parameters = new ArrayCollection();
    }
}
