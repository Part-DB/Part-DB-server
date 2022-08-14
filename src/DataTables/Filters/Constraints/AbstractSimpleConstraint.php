<?php

namespace App\DataTables\Filters\Constraints;

use App\DataTables\Filters\FilterInterface;
use Doctrine\ORM\QueryBuilder;

abstract class AbstractSimpleConstraint implements FilterInterface
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


    public function __construct(string $property, string $identifier = null)
    {
        $this->property = $property;
        $this->identifier = $identifier ?? $this->generateParameterIdentifier($property);
    }
}