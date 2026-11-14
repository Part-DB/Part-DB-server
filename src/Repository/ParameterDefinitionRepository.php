<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace App\Repository;

use App\Entity\Parameters\ParameterDefinition;

/**
 * @extends NamedDBElementRepository<ParameterDefinition>
 */
class ParameterDefinitionRepository extends NamedDBElementRepository
{
    /**
     * @return list<array{
     *     definition_id: int,
     *     name: string,
     *     symbol: string,
     *     unit: string,
     *     input_type: string,
     *     choices: list<string>|null,
     *     deprecated_choices: list<string>|null
     * }>
     */
    public function autocompleteForParameterEditor(string $name, int $max_results = 50): array
    {
        /** @var list<array{
         *     definition_id: int,
         *     name: string,
         *     alternative_names: string|null,
         *     symbol: string,
         *     unit: string,
         *     input_type: string,
         *     choices: list<string>|null,
         *     deprecated_choices: list<string>|null
         * }> $result
         */
        $result = $this->createQueryBuilder('definition')
            ->select('definition.id AS definition_id')
            ->addSelect('definition.name AS name')
            //Exposed so client side widgets (TomSelect) can match the alternative names, too. It is never rendered.
            ->addSelect('definition.alternative_names AS alternative_names')
            ->addSelect('definition.symbol AS symbol')
            ->addSelect('definition.unit AS unit')
            ->addSelect('definition.input_type AS input_type')
            ->addSelect('definition.choices AS choices')
            ->addSelect('definition.deprecated_choices AS deprecated_choices')
            ->where('ILIKE(definition.name, :name) = TRUE')
            ->orWhere('ILIKE(definition.alternative_names, :name) = TRUE')
            ->setParameter('name', '%'.$name.'%')
            ->orderBy('definition.name', 'ASC')
            ->setMaxResults($max_results)
            ->getQuery()
            ->getArrayResult();

        return $result;
    }
}
