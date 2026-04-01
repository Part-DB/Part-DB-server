<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

use Doctrine\ORM\QueryBuilder;

class ProjectSearchFilter implements FilterInterface
{
    /** @var boolean Whether to use regex for searching */
    protected bool $regex = false;

    /** @var bool Use name field for searching */
    protected bool $name = false;

    /** @var bool Use description for searching */
    protected bool $description = false;

    /** @var bool Use comment field for searching */
    protected bool $comment = false;

    /** @var bool Use status field for searching */
    protected bool $status = false;

    /**
     * If true, we search in the name of the parent project (if available).
     * This field is named "category" to keep the API consistent with PartSearchFilter and AssemblySearchFilter,
     * although projects don't have categories (they only have parents).
     */
    protected bool $category = false;

    /** @var bool Use dbId field for searching */
    protected bool $dbId = false;

    /** @var string The datasource used for searching */
    protected string $datasource = 'projects';

    protected static int $parameterCounter = 0;

    public function __construct(
        /** @var string The string to query for */
        protected string $keyword
    ) {
    }

    protected function generateParameterIdentifier(string $property): string
    {
        //Replace all special characters with underscores
        $property = preg_replace('/\W/', '_', $property);
        return $property . '_' . (self::$parameterCounter++) . '_';
    }

    protected function getFieldsToSearch(): array
    {
        $fields_to_search = [];

        if ($this->name) {
            $fields_to_search[] = 'project.name';
        }
        if ($this->description) {
            $fields_to_search[] = 'project.description';
        }
        if ($this->comment) {
            $fields_to_search[] = 'project.comment';
        }
        if ($this->status) {
            $fields_to_search[] = 'project.status';
        }
        if ($this->category) {
            // We search in the name of the parent project.
            // This is named category for consistency with PartSearchFilter and AssemblySearchFilter.
            $fields_to_search[] = '_search_parent.name';
        }
        if ($this->dbId) {
            $fields_to_search[] = 'project.id';
        }

        return $fields_to_search;
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        if ($this->category) {
            // We search in the parent project.
            // Check if the join alias is already present in the QueryBuilder
            $hasJoin = false;
            foreach ($queryBuilder->getDQLPart('join') as $joins) {
                foreach ($joins as $join) {
                    if ($join->getAlias() === '_search_parent') {
                        $hasJoin = true;
                        break 2;
                    }
                }
            }

            if (!$hasJoin) {
                $queryBuilder->leftJoin('project.parent', '_search_parent');
            }
        }

        $fields_to_search = $this->getFieldsToSearch();

        //If we have nothing to search for, do nothing
        if ($fields_to_search === [] || $this->keyword === '') {
            return;
        }

        $parameterIdentifier = $this->generateParameterIdentifier('search_query');

        //Convert the fields to search to a list of expressions
        $expressions = array_map(function (string $field) use ($parameterIdentifier): string {
            if ($this->regex) {
                return sprintf("REGEXP(%s, :%s) = TRUE", $field, $parameterIdentifier);
            }

            return sprintf("ILIKE(%s, :%s) = TRUE", $field, $parameterIdentifier);
        }, $fields_to_search);

        //Add Or concatenation of the expressions to our query
        $queryBuilder->andWhere(
            $queryBuilder->expr()->orX(...$expressions)
        );

        //For regex, we pass the query as is, for like we add % to the start and end as wildcards
        if ($this->regex) {
            $queryBuilder->setParameter($parameterIdentifier, $this->keyword);
        } else {
            //Escape % and _ characters in the keyword
            $keyword_escaped = str_replace(['%', '_'], ['\%', '\_'], $this->keyword);
            $queryBuilder->setParameter($parameterIdentifier, '%' . $keyword_escaped . '%');
        }
    }

    public function getKeyword(): string
    {
        return $this->keyword;
    }

    public function setKeyword(string $keyword): self
    {
        $this->keyword = $keyword;
        return $this;
    }

    public function isRegex(): bool
    {
        return $this->regex;
    }

    public function setRegex(bool $regex): self
    {
        $this->regex = $regex;
        return $this;
    }

    public function isName(): bool
    {
        return $this->name;
    }

    public function setName(bool $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function isDescription(): bool
    {
        return $this->description;
    }

    public function setDescription(bool $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function isComment(): bool
    {
        return $this->comment;
    }

    public function setComment(bool $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    public function isStatus(): bool
    {
        return $this->status;
    }

    public function setStatus(bool $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function isCategory(): bool
    {
        return $this->category;
    }

    /**
     * Set if the parent project name should be searched.
     * This is named "category" for consistency with PartSearchFilter and AssemblySearchFilter.
     */
    public function setCategory(bool $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function isMpn(): bool
    {
        return false;
    }

    public function setMpn(bool $mpn): self
    {
        return $this;
    }

    public function isTags(): bool
    {
        return false;
    }

    public function setTags(bool $tags): self
    {
        return $this;
    }

    public function isStorelocation(): bool
    {
        return false;
    }

    public function setStorelocation(bool $storelocation): self
    {
        return $this;
    }

    public function isSupplier(): bool
    {
        return false;
    }

    public function setSupplier(bool $supplier): self
    {
        return $this;
    }

    public function isManufacturer(): bool
    {
        return false;
    }

    public function setManufacturer(bool $manufacturer): self
    {
        return $this;
    }

    public function isFootprint(): bool
    {
        return false;
    }

    public function setFootprint(bool $footprint): self
    {
        return $this;
    }

    public function isDbId(): bool
    {
        return $this->dbId;
    }

    public function setDbId(bool $dbId): self
    {
        $this->dbId = $dbId;
        return $this;
    }

    public function isAssembly(): bool
    {
        return false;
    }

    public function setAssembly(bool $assembly): self
    {
        return $this;
    }

    public function isOrdernr(): bool
    {
        return false;
    }

    public function setOrdernr(bool $ordernr): self
    {
        return $this;
    }

    public function isIPN(): bool
    {
        return false;
    }

    public function setIPN(bool $ipn): self
    {
        return $this;
    }

    public function getDatasource(): string
    {
        return $this->datasource;
    }

    public function setDatasource(string $datasource): self
    {
        $this->datasource = $datasource;
        return $this;
    }
}
