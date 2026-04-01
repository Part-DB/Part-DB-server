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
use Doctrine\ORM\QueryBuilder;
use Doctrine\DBAL\ParameterType;

class PartSearchFilter implements FilterInterface
{

    /** @var boolean Whether to use regex for searching */
    protected bool $regex = false;

    /** @var bool Use name field for searching */
    protected bool $name = false;

    /** @var bool Use id field for searching */
    protected bool $dbId = false;

    /** @var bool Use category name for searching */
    protected bool $category = false;

    /** @var bool Use description for searching */
    protected bool $description = false;

    /** @var bool Use tags for searching */
    protected bool $tags = false;

    /** @var bool Use storelocation name for searching */
    protected bool $storelocation = false;

    /** @var bool Use comment field for searching */
    protected bool $comment = false;

    /** @var bool Use ordernr for searching */
    protected bool $ordernr = false;

    /** @var bool Use manufacturer product name for searching */
    protected bool $mpn = false;

    /** @var bool Use supplier name for searching */
    protected bool $supplier = false;

    /** @var bool Use manufacturer name for searching */
    protected bool $manufacturer = false;

    /** @var bool Use footprint name for searching */
    protected bool $footprint = false;

    /** @var bool Use manufacturing status for searching */
    protected bool $manufacturingStatus = false;

    /** @var bool Use Internal Part number for searching */
    protected bool $ipn = false;

    /** @var bool Use assembly name for searching */
    protected bool $assembly = false;

    /** @var string The datasource used for searching */
    protected string $datasource = 'parts';

    protected static int $parameterCounter = 0;

    public function __construct(
        /** @var string The string to query for */
        protected string $keyword
    )
    {
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

        if($this->name) {
            $fields_to_search[] = 'part.name';
        }
        if($this->category) {
            $fields_to_search[] = '_search_category.name';
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
            $fields_to_search[] = '_search_storelocations.name';
        }
        if($this->ordernr) {
            $fields_to_search[] = '_search_orderdetails.supplierpartnr';
        }
        if($this->mpn) {
            $fields_to_search[] = 'part.manufacturer_product_number';
        }
        if($this->supplier) {
            $fields_to_search[] = '_search_suppliers.name';
        }
        if($this->manufacturer) {
            $fields_to_search[] = '_search_manufacturer.name';
        }
        if($this->footprint) {
            $fields_to_search[] = '_search_footprint.name';
        }
        if($this->manufacturingStatus) {
            $fields_to_search[] = 'part.manufacturing_status';
        }
        if ($this->ipn) {
            $fields_to_search[] = 'part.ipn';
        }
        if ($this->assembly) {
            $fields_to_search[] = '_search_assembly.name';
            $fields_to_search[] = '_search_assembly.ipn';
        }

        return $fields_to_search;
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        if ($this->category) {
            $queryBuilder->leftJoin('part.category', '_search_category');
        }
        if ($this->storelocation) {
            $queryBuilder->leftJoin('part.partLots', '_search_partLots')
                ->leftJoin('_search_partLots.storage_location', '_search_storelocations');
        }
        if ($this->ordernr) {
            $queryBuilder->leftJoin('part.orderdetails', '_search_orderdetails');
        }
        if ($this->supplier) {
            if (!$this->ordernr) {
                $queryBuilder->leftJoin('part.orderdetails', '_search_orderdetails');
            }
            $queryBuilder->leftJoin('_search_orderdetails.supplier', '_search_suppliers');
        }
        if ($this->manufacturer) {
            $queryBuilder->leftJoin('part.manufacturer', '_search_manufacturer');
        }
        if ($this->footprint) {
            $queryBuilder->leftJoin('part.footprint', '_search_footprint');
        }
        if ($this->assembly) {
            $queryBuilder->leftJoin('part.assembly_bom_entries', '_search_assemblyBomEntries')
                ->leftJoin('_search_assemblyBomEntries.assembly', '_search_assembly');
        }

        $fields_to_search = $this->getFieldsToSearch();
        $is_numeric = preg_match('/^\d+$/', $this->keyword) === 1;

        // Add exact ID match only when the keyword is numeric
        $search_dbId = $is_numeric && (bool)$this->dbId;

        //If we have nothing to search for, do nothing
        if (($fields_to_search === [] && !$search_dbId) || $this->keyword === '') {
            return;
        }

        $parameterIdentifier = $this->generateParameterIdentifier('search_query');
        $expressions = [];

        if($fields_to_search !== []) {
            //Convert the fields to search to a list of expressions
            $expressions = array_map(function (string $field) use ($parameterIdentifier): string {
                if ($this->regex) {
                    return sprintf("REGEXP(%s, :%s) = TRUE", $field, $parameterIdentifier);
                }

                return sprintf("ILIKE(%s, :%s) = TRUE", $field, $parameterIdentifier);
            }, $fields_to_search);

            //For regex, we pass the query as is, for like we add % to the start and end as wildcards
            if ($this->regex) {
                $queryBuilder->setParameter($parameterIdentifier, $this->keyword);
            } else {
                //Escape % and _ characters in the keyword
                $keyword_escaped = str_replace(['%', '_'], ['\%', '\_'], $this->keyword);
                $queryBuilder->setParameter($parameterIdentifier, '%' . $keyword_escaped . '%');
            }
        }

        //Use equal expression to just search for exact numeric matches
        if ($search_dbId) {
            $idParameterIdentifier = $this->generateParameterIdentifier('id_exact');
            $expressions[] = $queryBuilder->expr()->eq('part.id', ':' . $idParameterIdentifier);
            $queryBuilder->setParameter($idParameterIdentifier, (int) $this->keyword,
                ParameterType::INTEGER);
        }

        //Guard condition
        if (!empty($expressions)) {
            //Add Or concatenation of the expressions to our query
            $queryBuilder->andWhere(
                $queryBuilder->expr()->orX(...$expressions)
            );
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

    public function isDbId(): bool
    {
        return $this->dbId;
    }

    public function setDbId(bool $dbId): PartSearchFilter
    {
        $this->dbId = $dbId;
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

    public function isManufacturingStatus(): bool
    {
        return $this->manufacturingStatus;
    }

    public function setManufacturingStatus(bool $manufacturingStatus): PartSearchFilter
    {
        $this->manufacturingStatus = $manufacturingStatus;
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

    public function isAssembly(): bool
    {
        return $this->assembly;
    }

    public function setAssembly(bool $assembly): PartSearchFilter
    {
        $this->assembly = $assembly;
        return $this;
    }

    /**
     * Dummy method for compatibility with assembly/project search options in Twig.
     */
    public function isStatus(): bool
    {
        return false;
    }

    /**
     * Dummy method for compatibility with assembly/project search options in Twig.
     */
    public function setStatus(bool $status): PartSearchFilter
    {
        return $this;
    }

    public function getDatasource(): string
    {
        return $this->datasource;
    }

    public function setDatasource(string $datasource): PartSearchFilter
    {
        $this->datasource = $datasource;
        return $this;
    }
}
