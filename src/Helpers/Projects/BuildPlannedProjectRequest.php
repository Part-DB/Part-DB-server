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
namespace App\Helpers\Projects;

use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\ProjectSystem\PlannedProject;
use App\Entity\ProjectSystem\Project;

/**
 * Represents the (much smaller) confirmation step for building an already-planned project: unlike a normal
 * build, the amounts to withdraw are already fixed by the plan's reservations, so this only collects a
 * comment and, if the source project has a "builds part", where the finished builds should be added to.
 */
final class BuildPlannedProjectRequest
{
    private string $comment = '';

    private ?PartLot $builds_lot = null;

    private bool $add_build_to_builds_part = false;

    public function __construct(private readonly PlannedProject $plannedProject)
    {
        $project = $plannedProject->getProject();

        //By default, use the first available lot of the builds part, if there is one.
        if ($project instanceof Project && $project->getBuildPart() instanceof Part) {
            $this->add_build_to_builds_part = true;
            foreach ($project->getBuildPart()->getPartLots() as $lot) {
                if (!$lot->isInstockUnknown()) {
                    $this->builds_lot = $lot;
                    break;
                }
            }
        }
    }

    public function getPlannedProject(): PlannedProject
    {
        return $this->plannedProject;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function setComment(string $comment): void
    {
        $this->comment = $comment;
    }

    public function getBuildsPartLot(): ?PartLot
    {
        return $this->builds_lot;
    }

    /**
     * Sets the part lot the finished builds should be added to. The lot must belong to the project's build part.
     * @return $this
     */
    public function setBuildsPartLot(?PartLot $new_part_lot): self
    {
        $project = $this->plannedProject->getProject();

        if ($new_part_lot instanceof PartLot && (!$project instanceof Project || $new_part_lot->getPart() !== $project->getBuildPart())) {
            throw new \InvalidArgumentException('The given part lot does not belong to the projects build part!');
        }

        if ($new_part_lot instanceof PartLot) {
            $this->add_build_to_builds_part = true;
        }

        $this->builds_lot = $new_part_lot;

        return $this;
    }

    public function getAddBuildsToBuildsPart(): bool
    {
        return $this->add_build_to_builds_part;
    }

    public function setAddBuildsToBuildsPart(bool $new_value): self
    {
        $this->add_build_to_builds_part = $new_value;

        if (!$new_value) {
            $this->builds_lot = null;
        }

        return $this;
    }
}
