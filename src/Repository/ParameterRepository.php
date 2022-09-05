<?php

namespace App\Repository;

class ParameterRepository extends DBElementRepository
{
    /**
     * Find parameters using a parameter name
     * @param  string  $name The name to search for
     * @param  bool  $exact True, if only exact names should match. False, if the name just needs to be contained in the parameter name
     * @param  int  $max_results
     * @return array
     */
    public function autocompleteParamName(string $name, bool $exact = false, int $max_results = 50): array
    {
        $qb = $this->createQueryBuilder('parameter');

        $qb->distinct()
            ->select('parameter.name')
            ->addSelect('parameter.symbol')
            ->addSelect('parameter.unit')
            ->where('parameter.name LIKE :name');
        if ($exact) {
            $qb->setParameter('name', $name);
        } else {
            $qb->setParameter('name', '%'.$name.'%');
        }

        $qb->setMaxResults($max_results);

        return $qb->getQuery()->getArrayResult();
    }
}