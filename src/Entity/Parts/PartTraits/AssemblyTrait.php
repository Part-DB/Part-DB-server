<?php

declare(strict_types=1);

namespace App\Entity\Parts\PartTraits;

use App\Entity\AssemblySystem\Assembly;
use App\Entity\AssemblySystem\AssemblyBOMEntry;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

trait AssemblyTrait
{
    /**
     * @var Collection<AssemblyBOMEntry> $assembly_bom_entries
     */
    #[ORM\OneToMany(mappedBy: 'part', targetEntity: AssemblyBOMEntry::class, cascade: ['remove'], orphanRemoval: true)]
    protected Collection $assembly_bom_entries;

    /**
     * @var Assembly|null If a assembly is set here, then this part is special and represents the builds of an assembly.
     */
    #[ORM\OneToOne(inversedBy: 'build_part', targetEntity: Assembly::class)]
    #[ORM\JoinColumn]
    protected ?Assembly $built_assembly = null;

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
     * Checks whether this part represents the builds of a assembly
     * @return bool True if it represents the builds, false if not
     */
    #[Groups(['part:read'])]
    public function isAssemblyBuildPart(): bool
    {
        return $this->built_assembly !== null;
    }

    /**
     * Returns the assembly that this part represents the builds of, or null if it doesn't
     */
    public function getBuiltAssembly(): ?Assembly
    {
        return $this->built_assembly;
    }


    /**
     * Sets the assembly that this part represents the builds of
     * @param Assembly|null $built_assembly The assembly that this part represents the builds of, or null if it is not a build part
     */
    public function setBuiltAssembly(?Assembly $built_assembly): self
    {
        $this->built_assembly = $built_assembly;
        return $this;
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
