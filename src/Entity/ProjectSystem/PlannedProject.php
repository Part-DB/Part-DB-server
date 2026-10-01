<?php

declare(strict_types=1);

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
namespace App\Entity\ProjectSystem;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Base\AbstractNamedDBElement;
use App\EntityListeners\TreeCacheInvalidationListener;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A PlannedProject represents a reservation of stock (of a Project's BOM parts) for a future build of that
 * project, without immediately consuming the stock. See PartLotReservation for the actual per-lot reservations.
 *
 * @see \App\Tests\Entity\ProjectSystem\PlannedProjectTest
 */
#[ORM\Entity]
#[ORM\EntityListeners([TreeCacheInvalidationListener::class])]
#[ORM\Table(name: 'planned_projects')]
#[ApiResource(
    operations: [
        new Get(security: 'is_granted("read", object)'),
        new GetCollection(security: 'is_granted("@planned_projects.read")'),
    ],
    normalizationContext: ['groups' => ['planned_project:read', 'api:basic:read'], 'openapi_definition_name' => 'Read'],
)]
class PlannedProject extends AbstractNamedDBElement
{
    /**
     * @var Project|null The project (BOM template) this plan was created from. Kept nullable so a built/cancelled
     *                    plan survives as history if the template project is later deleted.
     */
    #[Groups(['planned_project:read'])]
    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(name: 'id_project', nullable: true, onDelete: 'SET NULL')]
    protected ?Project $project = null;

    #[Groups(['planned_project:read'])]
    #[ORM\Column(type: Types::TEXT)]
    protected string $comment = '';

    #[Assert\Positive]
    #[Groups(['planned_project:read'])]
    #[ORM\Column(name: 'number_of_builds', type: Types::INTEGER)]
    protected int $numberOfBuilds = 1;

    #[Groups(['planned_project:read'])]
    #[ORM\Column(type: Types::STRING, length: 20, enumType: PlannedProjectStatus::class)]
    protected PlannedProjectStatus $status = PlannedProjectStatus::PLANNED;

    /**
     * @var Collection<int, PartLotReservation> The stock currently held back for this plan. Only a plan that is
     *                                          still PLANNED has reservations: building or cancelling it releases them.
     */
    #[ORM\OneToMany(targetEntity: PartLotReservation::class, mappedBy: 'plannedProject', cascade: ['persist', 'remove'], orphanRemoval: true)]
    protected Collection $reservations;

    /**
     * @var array<int, array{part_id: int|null, part_name: string, bom_entry_name: string|null, mountnames: string, needed: float, lots: array<int, array{name: string, storage_location: string|null, amount: float}>}>|null
     *      Snapshot of which parts (and from which lots) were actually consumed when this plan was built, so the
     *      plan keeps a record of that even after the parts, lots or the source project are changed or deleted.
     *      Null as long as the plan has not been built.
     */
    #[ORM\Column(name: 'built_parts', type: Types::JSON, nullable: true)]
    protected ?array $builtParts = null;

    public function __construct()
    {
        $this->reservations = new ArrayCollection();
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;
        return $this;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function setComment(string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    public function getNumberOfBuilds(): int
    {
        return $this->numberOfBuilds;
    }

    public function setNumberOfBuilds(int $numberOfBuilds): self
    {
        $this->numberOfBuilds = $numberOfBuilds;
        return $this;
    }

    public function getStatus(): PlannedProjectStatus
    {
        return $this->status;
    }

    public function setStatus(PlannedProjectStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return Collection<int, PartLotReservation>
     */
    public function getReservations(): Collection
    {
        return $this->reservations;
    }

    public function addReservation(PartLotReservation $reservation): self
    {
        $this->reservations->add($reservation);
        return $this;
    }

    public function removeReservation(PartLotReservation $reservation): self
    {
        $this->reservations->removeElement($reservation);
        return $this;
    }

    /**
     * @return array<int, array{part_id: int|null, part_name: string, bom_entry_name: string|null, mountnames: string, needed: float, lots: array<int, array{name: string, storage_location: string|null, amount: float}>}>|null
     */
    public function getBuiltParts(): ?array
    {
        return $this->builtParts;
    }

    /**
     * @param array<int, array{part_id: int|null, part_name: string, bom_entry_name: string|null, mountnames: string, needed: float, lots: array<int, array{name: string, storage_location: string|null, amount: float}>}>|null $builtParts
     */
    public function setBuiltParts(?array $builtParts): self
    {
        $this->builtParts = $builtParts;
        return $this;
    }

    /**
     * Returns the total amount that has to be reserved/built for the given BOM entry, based on the number of
     * planned builds. Mirrors ProjectBuildRequest::getNeededAmountForBOMEntry().
     */
    public function getRequiredAmountForBOMEntry(ProjectBOMEntry $entry): float
    {
        $amount = $entry->getQuantity() * $this->numberOfBuilds;

        if ($entry->getPart() && !$entry->getPart()->useFloatAmount()) {
            return round($amount);
        }

        return $amount;
    }

    /**
     * Returns the amount that is currently reserved by this plan for the given BOM entry (sum over all lots).
     */
    public function getReservedAmountForBOMEntry(ProjectBOMEntry $entry): float
    {
        $sum = 0.0;
        foreach ($this->reservations as $reservation) {
            if ($reservation->getBomEntry() === $entry) {
                $sum += $reservation->getAmount();
            }
        }

        return $sum;
    }
}
