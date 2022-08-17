<?php

namespace App\DataTables\Filters;

use App\DataTables\Filters\Constraints\BooleanConstraint;
use App\DataTables\Filters\Constraints\NumberConstraint;
use App\DataTables\Filters\Constraints\TextConstraint;
use Doctrine\ORM\QueryBuilder;

class PartFilter implements FilterInterface
{
    /** @var TextConstraint */
    protected $name;

    /** @var TextConstraint */
    protected $description;

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

    public function getName(): TextConstraint
    {
        return $this->name;
    }

    public function getDescription(): TextConstraint
    {
        return $this->description;
    }

    public function __construct()
    {
        $this->favorite = new BooleanConstraint('part.favorite');
        $this->needsReview = new BooleanConstraint('part.needs_review');
        $this->mass = new NumberConstraint('part.mass');
        $this->name = new TextConstraint('part.name');
        $this->description = new TextConstraint('part.description');
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        $this->favorite->apply($queryBuilder);
        $this->needsReview->apply($queryBuilder);
        $this->mass->apply($queryBuilder);
        $this->name->apply($queryBuilder);
        $this->description->apply($queryBuilder);
    }
}
