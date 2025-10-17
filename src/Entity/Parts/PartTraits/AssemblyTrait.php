<?php

declare(strict_types=1);

namespace App\Entity\Parts\PartTraits;

use App\Entity\AssemblySystem\Assembly;
use App\Entity\AssemblySystem\AssemblyBOMEntry;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

trait AssemblyTrait
{
    /**
     * @var Collection<AssemblyBOMEntry> $assembly_bom_entries
     */
    #[ORM\OneToMany(mappedBy: 'part', targetEntity: AssemblyBOMEntry::class, cascade: ['remove'], orphanRemoval: true)]
    protected Collection $assembly_bom_entries;

    /**
     *  Returns all AssemblyBOMEntry that use this part.
     *
     * @phpstan-return Collection<int, AssemblyBOMEntry>
     */
    public function getAssemblyBomEntries(): Collection
    {
        return $this->assembly_bom_entries;
    }

    /**
     * Get all assemblies which uses this part.
     *
     * @return Assembly[] all assemblies which uses this part as a one-dimensional array of Assembly objects
     */
    public function getAssemblies(): array
    {
        $assemblies = [];

        foreach($this->assembly_bom_entries as $entry) {
            $assemblies[] = $entry->getAssembly();
        }

        return $assemblies;
    }
}
