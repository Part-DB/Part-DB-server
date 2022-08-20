<?php

namespace App\DataTables\Filters;

use App\DataTables\Filters\Constraints\BooleanConstraint;
use App\DataTables\Filters\Constraints\DateTimeConstraint;
use App\DataTables\Filters\Constraints\EntityConstraint;
use App\DataTables\Filters\Constraints\NumberConstraint;
use App\DataTables\Filters\Constraints\TextConstraint;
use App\Entity\Parts\Category;
use App\Services\Trees\NodesListBuilder;
use Doctrine\ORM\QueryBuilder;

class PartFilter implements FilterInterface
{

    use CompoundFilterTrait;

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

    /** @var DateTimeConstraint */
    protected $lastModified;

    /** @var DateTimeConstraint */
    protected $addedDate;

    /** @var EntityConstraint */
    protected $category;

    public function __construct(NodesListBuilder $nodesListBuilder)
    {
        $this->favorite =
        $this->needsReview = new BooleanConstraint('part.needs_review');
        $this->mass = new NumberConstraint('part.mass');
        $this->name = new TextConstraint('part.name');
        $this->description = new TextConstraint('part.description');
        $this->addedDate = new DateTimeConstraint('part.addedDate');
        $this->lastModified = new DateTimeConstraint('part.lastModified');

        $this->category = new EntityConstraint($nodesListBuilder, Category::class, 'part.category');
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        $this->applyAllChildFilters($queryBuilder);
    }


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

    /**
     * @return DateTimeConstraint
     */
    public function getLastModified(): DateTimeConstraint
    {
        return $this->lastModified;
    }

    /**
     * @return DateTimeConstraint
     */
    public function getAddedDate(): DateTimeConstraint
    {
        return $this->addedDate;
    }

    public function getCategory(): EntityConstraint
    {
        return $this->category;
    }
}
