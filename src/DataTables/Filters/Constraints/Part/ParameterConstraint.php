<?php

namespace App\DataTables\Filters\Constraints\Part;

use App\DataTables\Filters\Constraints\AbstractConstraint;
use App\Entity\Parameters\PartParameter;
use Doctrine\ORM\QueryBuilder;

class ParameterConstraint extends AbstractConstraint
{
    /** @var string */
    protected $name;

    /** @var string */
    protected $symbol;

    /** @var string */
    protected $unit;

    public function __construct()
    {
        parent::__construct("parts.parameters");
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        //Create a new qb to build the subquery
        $subqb = new QueryBuilder($queryBuilder->getEntityManager());

        //The alias has to be uniq for each subquery, so generate a random one
        $alias = uniqid('param_', false);

        $subqb->select('COUNT(' . $alias . ')')
            ->from(PartParameter::class, $alias)
            ->where($alias . '.element = part');

        if (!empty($this->name)) {
            $paramName = $this->generateParameterIdentifier('params.name');
            $subqb->andWhere($alias . '.name = :' . $paramName);
            $queryBuilder->setParameter($paramName,  $this->name);
        }

        if (!empty($this->symbol)) {
            $paramName = $this->generateParameterIdentifier('params.symbol');
            $subqb->andWhere($alias . '.symbol = :' . $paramName);
            $queryBuilder->setParameter($paramName,  $this->symbol);
        }

        if (!empty($this->unit)) {
            $paramName = $this->generateParameterIdentifier('params.unit');
            $subqb->andWhere($alias . '.unit = :' . $paramName);
            $queryBuilder->setParameter($paramName,  $this->unit);
        }

        $queryBuilder->andWhere('(' . $subqb->getDQL() . ') > 0');
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param  string  $name
     * @return ParameterConstraint
     */
    public function setName(string $name): ParameterConstraint
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getSymbol(): string
    {
        return $this->symbol;
    }

    /**
     * @param  string  $symbol
     * @return ParameterConstraint
     */
    public function setSymbol(string $symbol): ParameterConstraint
    {
        $this->symbol = $symbol;
        return $this;
    }

    /**
     * @return string
     */
    public function getUnit(): string
    {
        return $this->unit;
    }

    /**
     * @param  string  $unit
     * @return ParameterConstraint
     */
    public function setUnit(string $unit): ParameterConstraint
    {
        $this->unit = $unit;
        return $this;
    }
}