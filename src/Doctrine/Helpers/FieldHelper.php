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


namespace App\Doctrine\Helpers;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\QueryBuilder;

/**
 * The purpose of this class is to provide help with using the FIELD functions in Doctrine, which depends on the database platform.
 */
final class FieldHelper
{
    /**
     * Add an ORDER BY FIELD expression to the query builder. The correct FIELD function is used depending on the database platform.
     * In this function an already bound paramater is used. If you want to a not already bound value, use the addOrderByFieldValues function.
     * @param  QueryBuilder  $qb The query builder to apply the order by to
     * @param  string  $field_expr The expression to compare with the values
     * @param  string|int  $bound_param The already bound parameter to use for the values
     * @param  string|null  $order The order direction (ASC or DESC)
     * @return QueryBuilder
     */
    public static function addOrderByFieldParam(QueryBuilder $qb, string $field_expr, string|int $bound_param, ?string $order = null): QueryBuilder
    {
        $db_platform = $qb->getEntityManager()->getConnection()->getDatabasePlatform();

        //If we are on MySQL, we can just use the FIELD function
        if ($db_platform instanceof AbstractMySQLPlatform ) {
            $param = (is_numeric($bound_param) ? '?' : ":").(string)$bound_param;
            $qb->orderBy("FIELD($field_expr, $param)", $order);
        } else { //Use the sqlite/portable version or postgresql
            //Retrieve the values from the bound parameter
            $param = $qb->getParameter($bound_param);
            if ($param === null) {
                throw new \InvalidArgumentException("The bound parameter $bound_param does not exist.");
            }

            //Generate a unique key from the field_expr
            $key = 'field2_' . (string) $bound_param;

            if ($db_platform instanceof PostgreSQLPlatform) {
                self::addPostgresOrderBy($qb, $field_expr, $key, $param->getValue(), $order);
            } else {
                self::addSqliteOrderBy($qb, $field_expr, $key, $param->getValue(), $order);
            }
        }

        return $qb;
    }


    private static function addPostgresOrderBy(QueryBuilder $qb, string $field_expr, string $key, array $values, ?string $order = null): void
    {
        //Use postgres native array_position function, to get the index of the value in the array
        //In the end it gives a similar result as the FIELD function
        $qb->orderBy("array_position(:$key, $field_expr)", $order);

        //Convert the values to a literal array, to overcome the problem of passing more than 100 parameters
        $values = array_map(fn($value) => is_string($value) ? "'$value'" : $value, $values);
        $literalArray = '{' . implode(',', $values) . '}';

        $qb->setParameter($key, $literalArray);
    }

    private static function addSqliteOrderBy(QueryBuilder $qb, string $field_expr, string $key, array $values, ?string $order = null): void
    {
        //Otherwise we emulate it using
        $qb->orderBy("LOCATE(CONCAT(',', $field_expr, ','), :$key)", $order);
        //The string must be padded with a comma on both sides, otherwise the search using INSTR will fail
        $qb->setParameter($key, ',' .implode(',', $values) . ',');
    }

    /**
     * Add an ORDER BY FIELD expression to the query builder. The correct FIELD function is used depending on the database platform.
     * In this function the values are passed as an array. If you want to reuse an existing bound parameter, use the addOrderByFieldParam function.
     * @param  QueryBuilder  $qb The query builder to apply the order by to
     * @param  string  $field_expr The expression to compare with the values
     * @param  array  $values The values to compare with the expression as array
     * @param  string|null  $order The order direction (ASC or DESC)
     * @return QueryBuilder
     */
    public static function addOrderByFieldValues(QueryBuilder $qb, string $field_expr, array $values, ?string $order = null): QueryBuilder
    {
        $db_platform = $qb->getEntityManager()->getConnection()->getDatabasePlatform();

        $key = 'field2_' . md5($field_expr);

        //If we are on MySQL, we can just use the FIELD function
        if ($db_platform instanceof AbstractMySQLPlatform) {
            $qb->orderBy("FIELD2($field_expr, :field_arr)", $order);
        } elseif ($db_platform instanceof PostgreSQLPlatform) {
            //Use the postgres native array_position function
            self::addPostgresOrderBy($qb, $field_expr, $key, $values, $order);
        } else {
            //Otherwise use the portable version using string concatenation
            self::addSqliteOrderBy($qb, $field_expr, $key, $values, $order);
        }

        $qb->setParameter($key, $values);

        return $qb;
    }
}