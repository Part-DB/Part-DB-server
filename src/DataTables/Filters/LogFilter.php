<?php

namespace App\DataTables\Filters;

use App\DataTables\Filters\Constraints\ChoiceConstraint;
use App\DataTables\Filters\Constraints\DateTimeConstraint;
use App\DataTables\Filters\Constraints\EntityConstraint;
use App\DataTables\Filters\Constraints\InstanceOfConstraint;
use App\DataTables\Filters\Constraints\IntConstraint;
use App\DataTables\Filters\Constraints\NumberConstraint;
use App\Entity\UserSystem\User;
use Doctrine\ORM\QueryBuilder;

class LogFilter implements FilterInterface
{
    use CompoundFilterTrait;

    /** @var DateTimeConstraint */
    protected $timestamp;

    /** @var IntConstraint */
    protected $dbId;

    /** @var ChoiceConstraint  */
    protected $level;

    /** @var InstanceOfConstraint */
    protected $eventType;

    /** @var ChoiceConstraint */
    protected $targetType;

    /** @var IntConstraint */
    protected $targetId;

    /** @var EntityConstraint */
    protected $user;

    public function __construct()
    {
        $this->timestamp = new DateTimeConstraint('log.timestamp');
        $this->dbId = new IntConstraint('log.id');
        $this->level = new ChoiceConstraint('log.level');
        $this->eventType = new InstanceOfConstraint('log');
        $this->user = new EntityConstraint(null, User::class, 'log.user');

        $this->targetType = new ChoiceConstraint('log.target_type');
        $this->targetId = new IntConstraint('log.target_id');
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        $this->applyAllChildFilters($queryBuilder);
    }

    /**
     * @return DateTimeConstraint
     */
    public function getTimestamp(): DateTimeConstraint
    {
        return $this->timestamp;
    }

    /**
     * @return IntConstraint|NumberConstraint
     */
    public function getDbId()
    {
        return $this->dbId;
    }

    /**
     * @return ChoiceConstraint
     */
    public function getLevel(): ChoiceConstraint
    {
        return $this->level;
    }

    /**
     * @return InstanceOfConstraint
     */
    public function getEventType(): InstanceOfConstraint
    {
        return $this->eventType;
    }

    /**
     * @return ChoiceConstraint
     */
    public function getTargetType(): ChoiceConstraint
    {
        return $this->targetType;
    }

    /**
     * @return IntConstraint
     */
    public function getTargetId(): IntConstraint
    {
        return $this->targetId;
    }

    public function getUser(): EntityConstraint
    {
        return $this->user;
    }


}