<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AssemblyTwigExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('has_assembly', [$this, 'hasAssembly']),
        ];
    }

    public function hasAssembly(array $bomEntries): bool
    {
        foreach ($bomEntries as $entry) {
            if ($entry->getAssembly() !== null) {
                return true;
            }
        }
        return false;
    }
}