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

use App\Entity\Parts\PartAssociation;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\Valid;
use Doctrine\ORM\Mapping as ORM;

trait AssociationTrait
{
    /**
     * @var Collection<PartAssociation> All associations where this part is the owner
     */
    #[Valid]
    #[ORM\OneToMany(mappedBy: 'owner', targetEntity: PartAssociation::class,
        cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['part:read', 'part:write'])]
    protected Collection $associated_parts_as_owner;

    /**
     * @var Collection<PartAssociation> All associations where this part is the owned/other part
     */
    #[Valid]
    #[ORM\OneToMany(mappedBy: 'other', targetEntity: PartAssociation::class,
        cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['part:read'])]
    protected Collection $associated_parts_as_other;

    /**
     * Returns all associations where this part is the owner.
     * @return Collection<PartAssociation>
     */
    public function getAssociatedPartsAsOwner(): Collection
    {
        return $this->associated_parts_as_owner;
    }

    /**
     * Add a new association where this part is the owner.
     * @param  PartAssociation  $association
     * @return $this
     */
    public function addAssociatedPartsAsOwner(PartAssociation $association): self
    {
        //Ensure that the association is really owned by this part
        $association->setOwner($this);

        $this->associated_parts_as_owner->add($association);
        return $this;
    }

    /**
     * Remove an association where this part is the owner.
     * @param  PartAssociation  $association
     * @return $this
     */
    public function removeAssociatedPartsAsOwner(PartAssociation $association): self
    {
        $this->associated_parts_as_owner->removeElement($association);
        return $this;
    }

    /**
     * Returns all associations where this part is the owned/other part.
     * If you want to modify the association, do it on the owning part
     * @return Collection<PartAssociation>
     */
    public function getAssociatedPartsAsOther(): Collection
    {
        return $this->associated_parts_as_other;
    }

    /**
     * Returns all associations where this part is the owned or other part.
     * @return Collection<PartAssociation>
     */
    public function getAssociatedPartsAll(): Collection
    {
        return new ArrayCollection(
            array_merge(
                $this->associated_parts_as_owner->toArray(),
                $this->associated_parts_as_other->toArray()
            )
        );
    }
}