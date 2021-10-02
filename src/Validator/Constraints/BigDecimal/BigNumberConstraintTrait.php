<?php

namespace App\Validator\Constraints\BigDecimal;

use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

trait BigNumberConstraintTrait
{
    private function configureNumberConstraintOptions($options): array
    {
        if (null === $options) {
            $options = [];
        } elseif (!\is_array($options)) {
            $options = [$this->getDefaultOption() => $options];
        }

        if (isset($options['propertyPath'])) {
            throw new ConstraintDefinitionException(sprintf('The "propertyPath" option of the "%s" constraint cannot be set.', static::class));
        }

        if (isset($options['value'])) {
            throw new ConstraintDefinitionException(sprintf('The "value" option of the "%s" constraint cannot be set.', static::class));
        }

        $options['value'] = 0;

        return $options;
    }
}