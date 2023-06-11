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
namespace App\DataTables\Filters\Constraints\Part;

use App\DataTables\Filters\Constraints\AbstractConstraint;
use App\DataTables\Filters\Constraints\TextConstraint;
use App\Entity\Parameters\PartParameter;
use Doctrine\ORM\QueryBuilder;

class ParameterConstraint extends AbstractConstraint
{
    /** @var string */
    protected string $name;

    /** @var string */
    protected string $symbol;

    /** @var string */
    protected string $unit;

    /** @var TextConstraint */
    protected TextConstraint $value_text;

    /** @var ParameterValueConstraint */
    protected ParameterValueConstraint $value;

    /** @var string The alias to use for the subquery */
    protected string $alias;

    public function __construct()
    {
        parent::__construct("parts.parameters");

        //The alias has to be uniq for each subquery, so generate a random one
        $this->alias = uniqid('param_', false);

        $this->value_text = new TextConstraint($this->alias . '.value_text');
        $this->value = new ParameterValueConstraint($this->alias );
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        //Create a new qb to build the subquery
        $subqb = new QueryBuilder($queryBuilder->getEntityManager());



        $subqb->select('COUNT(' . $this->alias . ')')
            ->from(PartParameter::class, $this->alias)
            ->where($this->alias . '.element = part');

        if ($this->name !== '') {
            $paramName = $this->generateParameterIdentifier('params.name');
            $subqb->andWhere($this->alias . '.name = :' . $paramName);
            $queryBuilder->setParameter($paramName,  $this->name);
        }

        if ($this->symbol !== '') {
            $paramName = $this->generateParameterIdentifier('params.symbol');
            $subqb->andWhere($this->alias . '.symbol = :' . $paramName);
            $queryBuilder->setParameter($paramName,  $this->symbol);
        }

        if ($this->unit !== '') {
            $paramName = $this->generateParameterIdentifier('params.unit');
            $subqb->andWhere($this->alias . '.unit = :' . $paramName);
            $queryBuilder->setParameter($paramName,  $this->unit);
        }

        //Apply all subfilters
        $this->value_text->apply($subqb);
        $this->value->apply($subqb);

        //Copy all parameters from the subquery to the main query
        //We can not use setParameters here, as this would override the exiting paramaters in queryBuilder
        foreach ($subqb->getParameters() as $parameter) {
            $queryBuilder->setParameter($parameter->getName(), $parameter->getValue());
        }

        $queryBuilder->andWhere('(' . $subqb->getDQL() . ') > 0');
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): ParameterConstraint
    {
        $this->name = $name;
        return $this;
    }

    public function getSymbol(): string
    {
        return $this->symbol;
    }

    public function setSymbol(string $symbol): ParameterConstraint
    {
        $this->symbol = $symbol;
        return $this;
    }

    public function getUnit(): string
    {
        return $this->unit;
    }

    public function setUnit(string $unit): ParameterConstraint
    {
        $this->unit = $unit;
        return $this;
    }

    public function getValueText(): TextConstraint
    {
        return $this->value_text;
    }

    public function getValue(): ParameterValueConstraint
    {
        return $this->value;
    }


}
