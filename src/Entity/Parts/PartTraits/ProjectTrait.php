<?php

namespace App\Entity\Parts\PartTraits;

use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

trait ProjectTrait
{
    /**
     * @var Collection<int, ProjectBOMEntry> $project_bom_entries
     * @ORM\OneToMany(targetEntity="App\Entity\ProjectSystem\ProjectBOMEntry", mappedBy="part", cascade={"remove"}, orphanRemoval=true)
     */
    protected $project_bom_entries = [];

    /**
     * @var Project|null If a project is set here, then this part is special and represents the builds of a project.
     * @ORM\OneToOne(targetEntity="App\Entity\ProjectSystem\Project", inversedBy="build_part")
     * @ORM\JoinColumn(nullable=true)
     */
    protected ?Project $built_project = null;

    /**
     * Returns all ProjectBOMEntries that use this part.
     * @return Collection<int, ProjectBOMEntry>|ProjectBOMEntry[]
     */
    public function getProjectBomEntries(): Collection
    {
        return $this->project_bom_entries;
    }

    /**
     * Checks whether this part represents the builds of a project
     * @return bool True if it represents the builds, false if not
     */
    public function isProjectBuildPart(): bool
    {
        return $this->built_project !== null;
    }

    /**
     * Returns the project that this part represents the builds of, or null if it doesn't
     * @return Project|null
     */
    public function getBuiltProject(): ?Project
    {
        return $this->built_project;
    }


    /**
     * Sets the project that this part represents the builds of
     * @param  Project|null  $built_project The project that this part represents the builds of, or null if it is not a build part
     */
    public function setBuiltProject(?Project $built_project): self
    {
        $this->built_project = $built_project;
        return $this;
    }


    /**
     *  Get all projects which uses this part.
     *
     * @return Project[] * all devices which uses this part as a one-dimensional array of Device objects
     *                  (empty array if there are no ones)
     *                  * the array is sorted by the devices names
     */
    public function getProjects(): array
    {
        $projects = [];

        foreach($this->project_bom_entries as $entry) {
            $projects[] = $entry->getProject();
        }

        return $projects;
    }
}