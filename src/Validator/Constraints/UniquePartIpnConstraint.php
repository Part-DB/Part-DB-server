<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 * @Target({"PROPERTY"})
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class UniquePartIpnConstraint extends Constraint
{
   public string $message = 'part.ipn.must_be_unique';

    public function validatedBy(): string
    {
        return UniquePartIpnValidator::class;
    }
}