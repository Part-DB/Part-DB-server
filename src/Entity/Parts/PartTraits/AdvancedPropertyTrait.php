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
use Doctrine\DBAL\Types\Types;
use App\Entity\Parts\Part;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
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
     * @var string A comma separated list of tags, associated with the part
     */
    #[Groups(['extended', 'full', 'import', 'part:read', 'part:write'])]
    #[ORM\Column(type: Types::TEXT)]
    protected string $tags = '';

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
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Length(max: 100)]
    #[UniquePartIpnConstraint]
    protected ?string $ipn = null;

    /**
     * @var InfoProviderReference The reference to the info provider, that provided the information about this part
     */
    #[ORM\Embedded(class: InfoProviderReference::class, columnPrefix: 'provider_reference_')]
    #[Groups(['full', 'part:read'])]
    protected InfoProviderReference $providerReference;

    /**
     * @var ?PartCustomState the custom state for the part
     */
    #[Groups(['extended', 'full', 'import', 'part:read', 'part:write'])]
    #[ORM\ManyToOne(targetEntity: PartCustomState::class)]
    #[ORM\JoinColumn(name: 'id_part_custom_state')]
    protected ?PartCustomState $partCustomState = null;

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
     * Gets a comma separated list, of tags, that are assigned to this part.
     */
    public function getTags(): string
    {
        return $this->tags;
    }

    /**
     * Sets a comma separated list of tags, that are assigned to this part.
     *
     * @param string $tags The new tags
     *
     * @return $this
     */
    public function setTags(string $tags): self
    {
        $this->tags = $tags;

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
}
