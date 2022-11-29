<?php
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

use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;

class PartSearchFilter implements FilterInterface
{

    /** @var string The string to query for */
    protected string $keyword;

    /** @var boolean Whether to use regex for searching */
    protected bool $regex = false;

    /** @var bool Use name field for searching */
    protected bool $name = true;

    /** @var bool Use category name for searching */
    protected bool $category = true;

    /** @var bool Use description for searching */
    protected bool $description = true;

    /** @var bool Use tags for searching */
    protected bool $tags = true;

    /** @var bool Use storelocation name for searching */
    protected bool $storelocation = true;

    /** @var bool Use comment field for searching */
    protected bool $comment = true;

    /** @var bool Use ordernr for searching */
    protected bool $ordernr = true;

    /** @var bool Use manufacturer product name for searching */
    protected bool $mpn = true;

    /** @var bool Use supplier name for searching */
    protected bool $supplier = false;

    /** @var bool Use manufacturer name for searching */
    protected bool $manufacturer = false;

    /** @var bool Use footprint name for searching */
    protected bool $footprint = false;

    public function __construct(string $query)
    {
        $this->keyword = $query;
    }

    protected function getFieldsToSearch(): array
    {
        $fields_to_search = [];

        if($this->name) {
            $fields_to_search[] = 'part.name';
        }
        if($this->category) {
            $fields_to_search[] = 'category.name';
        }
        if($this->description) {
            $fields_to_search[] = 'part.description';
        }
        if($this->tags) {
            $fields_to_search[] = 'part.tags';
        }
        if($this->storelocation) {
            $fields_to_search[] = 'storelocations.name';
        }
        if($this->ordernr) {
            $fields_to_search[] = 'orderdetails.supplierpartnr';
        }
        if($this->mpn) {
            $fields_to_search[] = 'part.manufacturer_product_url';
        }
        if($this->supplier) {
            $fields_to_search[] = 'suppliers.name';
        }
        if($this->manufacturer) {
            $fields_to_search[] = 'manufacturer.name';
        }
        if($this->footprint) {
            $fields_to_search[] = 'footprint.name';
        }

        return $fields_to_search;
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        $fields_to_search = $this->getFieldsToSearch();

        //If we have nothing to search for, do nothing
        if (empty($fields_to_search) || empty($this->keyword)) {
            return;
        }

        //Convert the fields to search to a list of expressions
        $expressions = array_map(function (string $field) {
            if ($this->regex) {
                return sprintf("REGEXP(%s, :search_query) = 1", $field);
            }

            return sprintf("%s LIKE :search_query", $field);
        }, $fields_to_search);

        //Add Or concatation of the expressions to our query
        $queryBuilder->andWhere(
            $queryBuilder->expr()->orX(...$expressions)
        );

        //For regex we pass the query as is, for like we add % to the start and end as wildcards
        if ($this->regex) {
            $queryBuilder->setParameter('search_query', $this->keyword);
        } else {
            $queryBuilder->setParameter('search_query', '%' . $this->keyword . '%');
        }
    }

    /**
     * @return string
     */
    public function getKeyword(): string
    {
        return $this->keyword;
    }

    /**
     * @param  string  $keyword
     * @return PartSearchFilter
     */
    public function setKeyword(string $keyword): PartSearchFilter
    {
        $this->keyword = $keyword;
        return $this;
    }

    /**
     * @return bool
     */
    public function isRegex(): bool
    {
        return $this->regex;
    }

    /**
     * @param  bool  $regex
     * @return PartSearchFilter
     */
    public function setRegex(bool $regex): PartSearchFilter
    {
        $this->regex = $regex;
        return $this;
    }

    /**
     * @return bool
     */
    public function isName(): bool
    {
        return $this->name;
    }

    /**
     * @param  bool  $name
     * @return PartSearchFilter
     */
    public function setName(bool $name): PartSearchFilter
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return bool
     */
    public function isCategory(): bool
    {
        return $this->category;
    }

    /**
     * @param  bool  $category
     * @return PartSearchFilter
     */
    public function setCategory(bool $category): PartSearchFilter
    {
        $this->category = $category;
        return $this;
    }

    /**
     * @return bool
     */
    public function isDescription(): bool
    {
        return $this->description;
    }

    /**
     * @param  bool  $description
     * @return PartSearchFilter
     */
    public function setDescription(bool $description): PartSearchFilter
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return bool
     */
    public function isTags(): bool
    {
        return $this->tags;
    }

    /**
     * @param  bool  $tags
     * @return PartSearchFilter
     */
    public function setTags(bool $tags): PartSearchFilter
    {
        $this->tags = $tags;
        return $this;
    }

    /**
     * @return bool
     */
    public function isStorelocation(): bool
    {
        return $this->storelocation;
    }

    /**
     * @param  bool  $storelocation
     * @return PartSearchFilter
     */
    public function setStorelocation(bool $storelocation): PartSearchFilter
    {
        $this->storelocation = $storelocation;
        return $this;
    }

    /**
     * @return bool
     */
    public function isOrdernr(): bool
    {
        return $this->ordernr;
    }

    /**
     * @param  bool  $ordernr
     * @return PartSearchFilter
     */
    public function setOrdernr(bool $ordernr): PartSearchFilter
    {
        $this->ordernr = $ordernr;
        return $this;
    }

    /**
     * @return bool
     */
    public function isMpn(): bool
    {
        return $this->mpn;
    }

    /**
     * @param  bool  $mpn
     * @return PartSearchFilter
     */
    public function setMpn(bool $mpn): PartSearchFilter
    {
        $this->mpn = $mpn;
        return $this;
    }

    /**
     * @return bool
     */
    public function isSupplier(): bool
    {
        return $this->supplier;
    }

    /**
     * @param  bool  $supplier
     * @return PartSearchFilter
     */
    public function setSupplier(bool $supplier): PartSearchFilter
    {
        $this->supplier = $supplier;
        return $this;
    }

    /**
     * @return bool
     */
    public function isManufacturer(): bool
    {
        return $this->manufacturer;
    }

    /**
     * @param  bool  $manufacturer
     * @return PartSearchFilter
     */
    public function setManufacturer(bool $manufacturer): PartSearchFilter
    {
        $this->manufacturer = $manufacturer;
        return $this;
    }

    /**
     * @return bool
     */
    public function isFootprint(): bool
    {
        return $this->footprint;
    }

    /**
     * @param  bool  $footprint
     * @return PartSearchFilter
     */
    public function setFootprint(bool $footprint): PartSearchFilter
    {
        $this->footprint = $footprint;
        return $this;
    }

    /**
     * @return bool
     */
    public function isComment(): bool
    {
        return $this->comment;
    }

    /**
     * @param  bool  $comment
     * @return PartSearchFilter
     */
    public function setComment(bool $comment): PartSearchFilter
    {
        $this->comment = $comment;
        return $this;
    }


}