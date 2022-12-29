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
     * Returns all ProjectBOMEntries that use this part.
     * @return Collection<int, ProjectBOMEntry>|ProjectBOMEntry[]
     */
    public function getProjectBomEntries(): Collection
    {
        return $this->project_bom_entries;
    }

    /**
     *  Get all devices which uses this part.
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