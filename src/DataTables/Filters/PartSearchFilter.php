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
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Parameter;
use Doctrine\DBAL\ParameterType;

class PartSearchFilter implements FilterInterface
{

    /** @var boolean Whether to use regex for searching */
    protected bool $regex = false;
    
    /** @var boolean Whether to use extensive matching for searching */
    protected bool $extensive = false;
    
    /** @var boolean Whether to use wildcards for searching */
    protected bool $wildcard = false;

    /** @var bool Use name field for searching */
    protected bool $name = true;

    /** @var bool Use id field for searching */
    protected bool $dbId = false;

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

    /** @var int array_map iteration helper variable */
    protected int $it = 0;

    public function __construct(
        /** @var string The string to query for */
        protected string $keyword
    ) {

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
        $is_numeric = preg_match('/^\d+$/', trim($this->keyword)) === 1;

        // Add exact ID match only when the keyword is numeric
        $search_dbId = $is_numeric && (bool)$this->dbId;

        $tokens = [];
        if ($this->extensive) {
            //Transform keyword and trim excess spaces
            $this->keyword = trim(str_replace('+', ' ', $this->keyword));
            //Split keyword on spaces, but limit token count to 5
            $tokens = explode(' ', $this->keyword, 5);
            //Throw away array elements which are null or have zero length
            $tokens = array_filter($tokens, fn($x) => (strlen($x) > 0));
        }
        else {
            //Pass the whole keyword into the (empty) tokens array as is,
            //retaining the original search behavior
            $tokens[] = $this->keyword;
        }

        //If we have nothing to search for...
        if (($fields_to_search === [] && !$search_dbId) || $this->keyword === '' || empty($tokens)) {
            // ...enforce returning no results
            $queryBuilder->add('where','1 = 0');
            return;
        }

        $expressions = [];
        $expressions2 = [];
        $params = [];

        //Search in selected fields, either based on regex or on tokenized keyword
        if ($fields_to_search !== []) {
            //For regex, we pass the query as is
            if ($this->regex) {
                //Convert the fields to search to a list of expressions
                $expressions = array_merge($expressions, array_map(function (string $field): string {
                        return sprintf("REGEXP(%s, :search_query) = TRUE", $field);
                }, $fields_to_search));
                $params[] = new Parameter('search_query', $this->keyword);
            } else {
                //Add a new expression and parameter set to the query for each token
                foreach ($tokens as $i => $token) {
                    //Conditionally escape % and _ characters
                    if (!$this->wildcard)
                        $token = str_replace(['%', '_'], ['\%', '\_'], $token);

                    //Convert the fields to search to a list of expressions
                    $tmp = array_fill_keys($fields_to_search, $i);
                    $expressions2 = array_map(function (string $field, int $idx): string {
                        return sprintf("ILIKE(%s, :search_query%u) = TRUE", $field, $idx);
                    }, array_keys($tmp), array_values($tmp));

                    //Aggregate the parameters for consolidated commission at the end
                    //For like, we add % to the start and end as wildcards
                    $params[] = new Parameter('search_query' . $i, '%' . $token . '%');

                    //Guard condition
                    if (!empty($expressions2)) {
                        //Add Or concatenation of the expressions to our query
                        $queryBuilder->andWhere(
                            $queryBuilder->expr()->orX(...$expressions2)
                        );
                    }
                }
            }
        }

        //Guard condition
        if (!empty($expressions)) {
            //Add Or concatenation of the expressions to our query
            $queryBuilder->andWhere(
                $queryBuilder->expr()->orX(...$expressions)
            );
       }
        //Use equal expression to search for exact numeric matches
        if ($search_dbId) {
            $queryBuilder->orWhere($queryBuilder->expr()->eq('part.id', ':id_exact'));
            $params[] = new Parameter('id_exact', (int)$this->keyword,
                ParameterType::INTEGER);
        }
        $queryBuilder->setParameters(
            new ArrayCollection($params)
        );
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

    public function isExtensive(): bool
    {
        return $this->extensive;
    }

    public function setExtensive(bool $extensive): PartSearchFilter
    {
        $this->extensive = $extensive;
        return $this;
    }


    public function isWildcard(): bool
    {
        return $this->wildcard;
    }

    public function setWildcard(bool $wildcard): PartSearchFilter
    {
        $this->wildcard = $wildcard;
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
