<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */
namespace App\DataTables\Filters;

use App\DataTables\Filters\Constraints\AbstractConstraint;
use App\DataTables\Filters\Constraints\ChoiceConstraint;
use App\DataTables\Filters\Constraints\DateTimeConstraint;
use App\DataTables\Filters\Constraints\EntityConstraint;
use App\DataTables\Filters\Constraints\InstanceOfConstraint;
use App\DataTables\Filters\Constraints\IntConstraint;
use App\Entity\UserSystem\User;
use Doctrine\ORM\QueryBuilder;

class LogFilter implements FilterInterface
{
    use CompoundFilterTrait;

    public readonly DateTimeConstraint $timestamp;
    public readonly IntConstraint $dbId;
    public readonly ChoiceConstraint $level;
    public readonly InstanceOfConstraint $eventType;
    public readonly ChoiceConstraint $targetType;
    public readonly IntConstraint $targetId;
    public readonly EntityConstraint $user;

    public function __construct()
    {
        //Must be done for every new set of attachment filters, to ensure deterministic parameter names.
        AbstractConstraint::resetParameterCounter();

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
}
