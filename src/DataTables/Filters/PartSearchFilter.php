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
use Doctrine\ORM\QueryBuilder;

class PartSearchFilter implements FilterInterface
{

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

    /** @var bool Use Internal Part number for searching */
    protected bool $ipn = true;

    public function __construct(
        /** @var string The string to query for */
        protected string $keyword
    )
    {
    }

    protected function getFieldsToSearch(): array
    {
        $fields_to_search = [];

        if($this->name) {
            $fields_to_search[] = 'part.name';
        }
        if($this->category) {
            $fields_to_search[] = '_category.name';
        }
        if($this->description) {
            $fields_to_search[] = 'part.description';
        }
        if ($this->comment) {
            $fields_to_search[] = 'part.comment';
        }
        if($this->tags) {
            $fields_to_search[] = 'part.tags';
        }
        if($this->storelocation) {
            $fields_to_search[] = '_storelocations.name';
        }
        if($this->ordernr) {
            $fields_to_search[] = '_orderdetails.supplierpartnr';
        }
        if($this->mpn) {
            $fields_to_search[] = 'part.manufacturer_product_number';
        }
        if($this->supplier) {
            $fields_to_search[] = '_suppliers.name';
        }
        if($this->manufacturer) {
            $fields_to_search[] = '_manufacturer.name';
        }
        if($this->footprint) {
            $fields_to_search[] = '_footprint.name';
        }
        if ($this->ipn) {
            $fields_to_search[] = 'part.ipn';
        }

        return $fields_to_search;
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        $fields_to_search = $this->getFieldsToSearch();

        //If we have nothing to search for, do nothing
        if ($fields_to_search === [] || $this->keyword === '') {
            return;
        }

        //Convert the fields to search to a list of expressions
        $expressions = array_map(function (string $field): string {
            if ($this->regex) {
                return sprintf("REGEXP(%s, :search_query) = TRUE", $field);
            }

            return sprintf("ILIKE(%s, :search_query) = TRUE", $field);
        }, $fields_to_search);

        //Add Or concatenation of the expressions to our query
        $queryBuilder->andWhere(
            $queryBuilder->expr()->orX(...$expressions)
        );

        //For regex, we pass the query as is, for like we add % to the start and end as wildcards
        if ($this->regex) {
            $queryBuilder->setParameter('search_query', $this->keyword);
        } else {
            $queryBuilder->setParameter('search_query', '%' . $this->keyword . '%');
        }
    }

    public function getKeyword(): string
    {
        return $this->keyword;
    }

    public function setKeyword(string $keyword): PartSearchFilter
    {
        $this->keyword = $keyword;
        return $this;
    }

    public function isRegex(): bool
    {
        return $this->regex;
    }

    public function setRegex(bool $regex): PartSearchFilter
    {
        $this->regex = $regex;
        return $this;
    }

    public function isName(): bool
    {
        return $this->name;
    }

    public function setName(bool $name): PartSearchFilter
    {
        $this->name = $name;
        return $this;
    }

    public function isCategory(): bool
    {
        return $this->category;
    }

    public function setCategory(bool $category): PartSearchFilter
    {
        $this->category = $category;
        return $this;
    }

    public function isDescription(): bool
    {
        return $this->description;
    }

    public function setDescription(bool $description): PartSearchFilter
    {
        $this->description = $description;
        return $this;
    }

    public function isTags(): bool
    {
        return $this->tags;
    }

    public function setTags(bool $tags): PartSearchFilter
    {
        $this->tags = $tags;
        return $this;
    }

    public function isStorelocation(): bool
    {
        return $this->storelocation;
    }

    public function setStorelocation(bool $storelocation): PartSearchFilter
    {
        $this->storelocation = $storelocation;
        return $this;
    }

    public function isOrdernr(): bool
    {
        return $this->ordernr;
    }

    public function setOrdernr(bool $ordernr): PartSearchFilter
    {
        $this->ordernr = $ordernr;
        return $this;
    }

    public function isMpn(): bool
    {
        return $this->mpn;
    }

    public function setMpn(bool $mpn): PartSearchFilter
    {
        $this->mpn = $mpn;
        return $this;
    }

    public function isIPN(): bool
    {
        return $this->ipn;
    }

    public function setIPN(bool $ipn): PartSearchFilter
    {
        $this->ipn = $ipn;
        return $this;
    }

    public function isSupplier(): bool
    {
        return $this->supplier;
    }

    public function setSupplier(bool $supplier): PartSearchFilter
    {
        $this->supplier = $supplier;
        return $this;
    }

    public function isManufacturer(): bool
    {
        return $this->manufacturer;
    }

    public function setManufacturer(bool $manufacturer): PartSearchFilter
    {
        $this->manufacturer = $manufacturer;
        return $this;
    }

    public function isFootprint(): bool
    {
        return $this->footprint;
    }

    public function setFootprint(bool $footprint): PartSearchFilter
    {
        $this->footprint = $footprint;
        return $this;
    }

    public function isComment(): bool
    {
        return $this->comment;
    }

    public function setComment(bool $comment): PartSearchFilter
    {
        $this->comment = $comment;
        return $this;
    }


}
