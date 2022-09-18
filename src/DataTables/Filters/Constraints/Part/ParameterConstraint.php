<?php

namespace App\DataTables\Filters\Constraints\Part;

use App\DataTables\Filters\Constraints\AbstractConstraint;
use App\DataTables\Filters\Constraints\TextConstraint;
use App\Entity\Parameters\PartParameter;
use Doctrine\ORM\QueryBuilder;
use Svg\Tag\Text;

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

        if (!empty($this->name)) {
            $paramName = $this->generateParameterIdentifier('params.name');
            $subqb->andWhere($this->alias . '.name = :' . $paramName);
            $queryBuilder->setParameter($paramName,  $this->name);
        }

        if (!empty($this->symbol)) {
            $paramName = $this->generateParameterIdentifier('params.symbol');
            $subqb->andWhere($this->alias . '.symbol = :' . $paramName);
            $queryBuilder->setParameter($paramName,  $this->symbol);
        }

        if (!empty($this->unit)) {
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

    /**
     * @return TextConstraint
     */
    public function getValueText(): TextConstraint
    {
        return $this->value_text;
    }

    /**
     * @return ParameterValueConstraint
     */
    public function getValue(): ParameterValueConstraint
    {
        return $this->value;
    }


}