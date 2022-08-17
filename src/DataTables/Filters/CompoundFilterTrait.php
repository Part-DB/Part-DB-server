<?php

namespace App\DataTables\Filters;

use Doctrine\ORM\QueryBuilder;

trait CompoundFilterTrait
{

    /**
     * Find all child filters that are contained in this filter using reflection.
     * A map is returned to the form "property_name" => $filter_object
     * @return FilterInterface[]
     */
    protected function findAllChildFilters(): array
    {
        $filters = [];
        $reflection = new \ReflectionClass($this);

        foreach ($reflection->getProperties() as $property) {
            $value = $property->getValue($this);
            //We only want filters (objects implementing FilterInterface)
            if($value instanceof FilterInterface) {
                $filters[$property->getName()] = $value;
            }
        }
        return $filters;
    }

    /**
     * Applies all children filters that are declared as property of this filter using reflection.
     * @param  QueryBuilder  $queryBuilder
     * @return void
     */
    protected function applyAllChildFilters(QueryBuilder $queryBuilder): void
    {
        //Retrieve all child filters and apply them
        $filters = $this->findAllChildFilters();

        foreach ($filters as $filter) {
            $filter->apply($queryBuilder);
        }
    }
}