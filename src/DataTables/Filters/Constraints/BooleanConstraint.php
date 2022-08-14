<?php

namespace App\DataTables\Filters\Constraints;

use App\DataTables\Filters\FilterInterface;
use Doctrine\ORM\QueryBuilder;

class BooleanConstraint extends AbstractSimpleConstraint
{
    /** @var bool|null The value of our constraint */
    protected $value;


    public function __construct(string $property, string $identifier = null, ?bool $default_value = null)
    {
        parent::__construct($property, $identifier, $default_value);
    }

    /**
     * Gets the value of this constraint. Null means "don't filter", true means "filter for true", false means "filter for false".
     * @return bool|null
     */
    public function getValue(): ?bool
    {
        return $this->value;
    }

    /**
     * Sets the value of this constraint. Null means "don't filter", true means "filter for true", false means "filter for false".
     * @param  bool|null  $value
     */
    public function setValue(?bool $value): void
    {
        $this->value = $value;
    }



    public function apply(QueryBuilder $queryBuilder): void
    {
        //Do not apply a filter if value is null (filter is set to ignore)
        if($this->value === null) {
            return;
        }

        $this->addSimpleAndConstraint($queryBuilder, $this->property, $this->identifier, '=', $this->value);
    }
}