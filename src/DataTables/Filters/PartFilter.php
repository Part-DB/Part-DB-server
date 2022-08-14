<?php

namespace App\DataTables\Filters;

use App\DataTables\Filters\Constraints\BooleanConstraint;
use App\DataTables\Filters\Constraints\NumberConstraint;
use Doctrine\ORM\QueryBuilder;

class PartFilter implements FilterInterface
{
    /** @var BooleanConstraint */
    protected $favorite;

    /** @var BooleanConstraint */
    protected $needsReview;

    /** @var NumberConstraint */
    protected $mass;

    /**
     * @return BooleanConstraint|false
     */
    public function getFavorite()
    {
        return $this->favorite;
    }

    /**
     * @return BooleanConstraint
     */
    public function getNeedsReview(): BooleanConstraint
    {
        return $this->needsReview;
    }

    public function getMass(): NumberConstraint
    {
        return $this->mass;
    }

    public function __construct()
    {
        $this->favorite = new BooleanConstraint('part.favorite');
        $this->needsReview = new BooleanConstraint('part.needs_review');
        $this->mass = new NumberConstraint('part.mass');
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        $this->favorite->apply($queryBuilder);
        $this->needsReview->apply($queryBuilder);
        $this->mass->apply($queryBuilder);
    }
}
