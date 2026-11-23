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

namespace App\Entity\Parts\PartTraits;

use App\Entity\Parts\InfoProviderReference;
use App\Entity\Parts\PartCustomState;
use App\Entity\Parts\Tag;
use App\Validator\Constraints\ValidGTIN;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use App\Entity\Parts\Part;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Length;
use App\Validator\Constraints\UniquePartIpnConstraint;

/**
 * Advanced properties of a part, not related to a more specific group.
 */
trait AdvancedPropertyTrait
{
    /**
     * @var bool Determines if this part entry needs review (for example, because it is work in progress)
     */
    #[Groups(['extended', 'full', 'import', 'part:read', 'part:write'])]
    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $needs_review = false;

    /**
     * @var Collection<int, Tag> The tags assigned to this part.
     * They are usually assigned and read by their names, through setTags() and getTags(), so the API, the import, the
     * part form and the info providers keep working with a comma-separated list of tag names.
     */
    #[ORM\ManyToMany(targetEntity: Tag::class, cascade: ['persist'])]
    #[ORM\JoinTable(name: 'parts_tags')]
    #[ORM\JoinColumn(name: 'part_id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'tag_id', onDelete: 'CASCADE')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    protected Collection $tagEntities;

    /**
     * @var float|null How much a single part unit weighs in grams
     */
    #[Assert\PositiveOrZero]
    #[Groups(['extended', 'full', 'import', 'part:read', 'part:write'])]
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    protected ?float $mass = null;

    /**
     * @var string|null The internal part number of the part
     */
    #[Assert\Length(max: 100)]
    #[Groups(['extended', 'full', 'import', 'part:read', 'part:write'])]
    #[ORM\Column(type: Types::STRING, length: 100, unique: true, nullable: true)]
    #[Length(max: 100)]
    #[UniquePartIpnConstraint]
    protected ?string $ipn = null;

    /**
     * @var InfoProviderReference The reference to the info provider, that provided the information about this part
     */
    #[ORM\Embedded(class: InfoProviderReference::class, columnPrefix: 'provider_reference_')]
    #[Groups(['full', 'part:read'])]
    #[Assert\Valid()]
    protected InfoProviderReference $providerReference;

    /**
     * @var ?PartCustomState the custom state for the part
     */
    #[Groups(['extended', 'full', 'import', 'part:read', 'part:write'])]
    #[ORM\ManyToOne(targetEntity: PartCustomState::class)]
    #[ORM\JoinColumn(name: 'id_part_custom_state')]
    protected ?PartCustomState $partCustomState = null;

    /**
     * @var string|null The GTIN (Global Trade Item Number) of the part, for example a UPC or EAN code
     */
    #[Groups(['extended', 'full', 'import', 'part:read', 'part:write'])]
    #[ORM\Column(type: Types::STRING, nullable: true)]
    #[ValidGTIN]
    protected ?string $gtin = null;

    /**
     * Checks if this part is marked, for that it needs further review.
     */
    public function isNeedsReview(): bool
    {
        return $this->needs_review;
    }

    /**
     * Sets the "needs review" status of this part.
     *
     * @param bool $needs_review The new status
     *
     * @return Part|self
     */
    public function setNeedsReview(bool $needs_review): self
    {
        $this->needs_review = $needs_review;

        return $this;
    }

    /**
     * Gets a comma separated list of the names of the tags, that are assigned to this part.
     */
    #[Groups(['extended', 'full', 'import', 'part:read', 'part:write'])]
    public function getTags(): string
    {
        return implode(Tag::SEPARATOR, $this->getTagNames());
    }

    /**
     * Assigns the tags with the given names to this part, given as a comma separated list.
     *
     * Tags which are already assigned are kept, the others are removed. A name without a tag assigned yet is added as
     * a new tag, which is replaced by the existing tag of that name (compared case-insensitively) when the part is
     * saved, see TagAssignmentListener.
     *
     * @param string $tags The names of the new tags, separated by commas
     *
     * @return $this
     */
    #[Groups(['extended', 'full', 'import', 'part:write'])]
    public function setTags(string $tags): self
    {
        $wanted = [];
        foreach (Tag::splitNames($tags) as $name) {
            $wanted[Tag::nameKey($name)] = $name;
        }

        foreach ($this->tagEntities->toArray() as $tag) {
            $key = Tag::nameKey($tag->getName());
            if (isset($wanted[$key])) {
                unset($wanted[$key]);
            } else {
                $this->tagEntities->removeElement($tag);
            }
        }

        foreach ($wanted as $name) {
            $this->tagEntities->add(new Tag($name));
        }

        return $this;
    }

    /**
     * Returns the names of the tags assigned to this part, in natural order.
     * @return list<string>
     */
    public function getTagNames(): array
    {
        $names = $this->tagEntities->map(static fn(Tag $tag): string => $tag->getName())->toArray();
        usort($names, strnatcasecmp(...));

        return $names;
    }

    /**
     * Returns the tags assigned to this part.
     * @return Collection<int, Tag>
     */
    public function getTagEntities(): Collection
    {
        return $this->tagEntities;
    }

    /**
     * Assigns the given tag to this part, if it is not assigned already.
     *
     * @return $this
     */
    public function addTagEntity(Tag $tag): self
    {
        if (!$this->tagEntities->contains($tag)) {
            $this->tagEntities->add($tag);
        }

        return $this;
    }

    /**
     * Removes the given tag from this part.
     *
     * @return $this
     */
    public function removeTagEntity(Tag $tag): self
    {
        $this->tagEntities->removeElement($tag);

        return $this;
    }

    /**
     * Returns the mass of a single part unit.
     * Returns null, if the mass is unknown/not set yet.
     */
    public function getMass(): ?float
    {
        return $this->mass;
    }

    /**
     * Sets the mass of a single part unit.
     * Sett to null, if the mass is unknown.
     *
     * @param float|null $mass the new mass
     *
     * @return $this
     */
    public function setMass(?float $mass): self
    {
        $this->mass = $mass;

        return $this;
    }

    /**
     * Returns the internal part number of the part.
     * @return string
     */
    public function getIpn(): ?string
    {
        return $this->ipn;
    }

    /**
     * Sets the internal part number of the part
     * @param  string  $ipn The new IPN of the part
     */
    public function setIpn(?string $ipn): Part
    {
        $this->ipn = $ipn;
        return $this;
    }

    /**
     * Returns the reference to the info provider, that provided the information about this part.
     * @return InfoProviderReference
     */
    public function getProviderReference(): InfoProviderReference
    {
        return $this->providerReference;
    }

    /**
     * Sets the reference to the info provider, that provided the information about this part.
     * @param  InfoProviderReference  $providerReference
     * @return Part
     */
    public function setProviderReference(InfoProviderReference $providerReference): Part
    {
        $this->providerReference = $providerReference;
        return $this;
    }

    /**
     * Gets the custom part state for the part
     * Returns null if no specific part state is set.
     */
    public function getPartCustomState(): ?PartCustomState
    {
        return $this->partCustomState;
    }

    /**
     * Sets the custom part state.
     *
     * @return $this
     */
    public function setPartCustomState(?PartCustomState $partCustomState): self
    {
        $this->partCustomState = $partCustomState;

        return $this;
    }

    /**
     * Gets the GTIN (Global Trade Item Number) of the part, for example a UPC or EAN code.
     * Returns null if no GTIN is set.
     */
    public function getGtin(): ?string
    {
        return $this->gtin;
    }

    /**
     * Sets the GTIN (Global Trade Item Number) of the part, for example a UPC or EAN code.
     *
     * @param string|null $gtin The new GTIN of the part
     *
     * @return $this
     */
    public function setGtin(?string $gtin): self
    {
        $this->gtin = $gtin;
        return $this;
    }
}
