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

declare(strict_types=1);


namespace App\DataTables\Adapters;

use App\DataTables\Events\ORMPostQueryEvent;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\QueryBuilder;
use Omines\DataTablesBundle\Adapter\AdapterQuery;
use Omines\DataTablesBundle\Adapter\Doctrine\Event\ORMAdapterQueryEvent;
use Omines\DataTablesBundle\Adapter\Doctrine\ORMAdapter;
use Omines\DataTablesBundle\Adapter\Doctrine\ORMAdapterEvents;
use Omines\DataTablesBundle\Column\AbstractColumn;

/**
 * This class is very similar to the original ORMAdapter, but instead of getting each line one by one, it gets all the results at once,
 * which can save some time in combination with fetch hints.
 */
class FetchResultsAtOnceORMAdapter extends ORMAdapter
{
    protected function getResults(AdapterQuery $query): \Traversable
    {
        /** @var QueryBuilder $builder */
        $builder = $query->get('qb');
        $state = $query->getState();

        // Apply definitive view state for current 'page' of the table
        foreach ($state->getOrderBy() as list($column, $direction)) {
            /** @var AbstractColumn $column */
            if ($column->isOrderable()) {
                $builder->addOrderBy($column->getOrderField(), $direction);
            }
        }
        if (null !== $state->getLength()) {
            $builder
                ->setFirstResult($state->getStart())
                ->setMaxResults($state->getLength())
            ;
        }

        $q = $builder->getQuery();
        $event = new ORMAdapterQueryEvent($q);
        $state->getDataTable()->getEventDispatcher()->dispatch($event, ORMAdapterEvents::PRE_QUERY);

        foreach ($q->getResult() as $item) {
            yield $item;
        }
    }
}