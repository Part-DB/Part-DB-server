<?php

declare(strict_types=1);

namespace App\Validator\Constraints\AssemblySystem;

use Symfony\Component\Validator\Constraint;

/**
 * This constraint ensures that no BOM entries in the assembly reference its own children.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class AssemblyInvalidBomEntry extends Constraint
{
    public string $message = 'assembly.bom_entry.invalid_child_entry';

    public function validatedBy(): string
    {
        return AssemblyInvalidBomEntryValidator::class;
    }
}