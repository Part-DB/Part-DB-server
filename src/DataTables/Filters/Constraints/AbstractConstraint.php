<?php

namespace App\DataTables\Filters\Constraints;

use App\DataTables\Filters\FilterInterface;
use Doctrine\ORM\QueryBuilder;

abstract class AbstractConstraint implements FilterInterface
{
    use FilterTrait;

    /**
     * @var string The property where this BooleanConstraint should apply to
     */
    protected $property;

    /**
     * @var string
     */
    protected $identifier;


    /**
     * Determines whether this constraint is active or not. This should be decided accordingly to the value of the constraint
     * @return bool True if the constraint is active, false otherwise
     */
    abstract public function isEnabled(): bool;

    public function __construct(string $property, string $identifier = null)
    {
        $this->property = $property;
        $this->identifier = $identifier ?? $this->generateParameterIdentifier($property);
    }
}