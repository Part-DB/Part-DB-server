<?php

namespace App\Validator\Constraints;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class UniquePartIpnConstraint extends Constraint
{
    public string $message = 'part.ipn.must_be_unique';

    public function getTargets(): string|array
    {
        return [self::CLASS_CONSTRAINT, self::PROPERTY_CONSTRAINT];
    }

    public function validatedBy(): string
    {
        return UniquePartIpnValidator::class;
    }
}
