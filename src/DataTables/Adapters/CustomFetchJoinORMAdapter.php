<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\DataTables\Adapters;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Omines\DataTablesBundle\Adapter\Doctrine\FetchJoinORMAdapter;

/**
 * This class is a workaround for a bug (or edge case behavior) in the FetchJoinORMAdapter or better the used Paginator
 * and CountOutputWalker.
 * If the query contains multiple GROUP BY clauses, the result of the count query is wrong, as some lines are counted
 * multiple times. This is because the CountOutputWalker does not use DISTINCT in the count query, if it contains a GROUP BY.
 *
 * We work around this by removing the GROUP BY clause from the query, and only adding the first root alias as GROUP BY (the part table).
 * This way we get the correct count, without breaking the query (we need a GROUP BY for the HAVING clauses).
 *
 * As a side effect this also seems to improve the performance of the count query a bit (which makes up a lot of the total query time).
 */
class CustomFetchJoinORMAdapter extends FetchJoinORMAdapter
{
    public function getCount(QueryBuilder $queryBuilder, $identifier): ?int
    {
        $qb_without_group_by = clone $queryBuilder;

        //Remove the groupBy clause from the query for the count
        //And add the root alias as group by, so we can use HAVING clauses
        $qb_without_group_by->resetDQLPart('groupBy');
        $qb_without_group_by->addGroupBy($queryBuilder->getRootAliases()[0]);

        $paginator = new Paginator($qb_without_group_by);

        return $paginator->count();
    }
}