<?php

namespace App\Twig;

use App\Entity\AssemblySystem\AssemblyBOMEntry;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AssemblyTwigExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('has_project', [$this, 'hasProject']),
        ];
    }

    /**
     * @param AssemblyBOMEntry[] $bomEntries
     */
    public function hasProject(array $bomEntries): bool
    {
        foreach ($bomEntries as $entry) {
            if ($entry->getProject() !== null) {
                return true;
            }
        }
        return false;
    }
}