<?php

namespace App\DataTables\Filters\Constraints;

use Doctrine\ORM\QueryBuilder;

class IntConstraint extends NumberConstraint
{
    public function apply(QueryBuilder $queryBuilder): void
    {
        if($this->value1 !== null) {
            $this->value1 = (int) $this->value1;
        }
        if($this->value2 !== null) {
            $this->value2 = (int) $this->value2;
        }

        parent::apply($queryBuilder);
    }
}