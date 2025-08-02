<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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
use App\Entity\BulkInfoProviderImportJobPart;
use Doctrine\ORM\QueryBuilder;

class BulkImportJobStatusConstraint extends AbstractConstraint
{
    /** @var array The status values to filter by */
    protected array $values = [];

    /** @var string|null The operator to use ('any_of', 'none_of', 'all_of') */
    protected ?string $operator = null;

    public function __construct()
    {
        parent::__construct('bulk_import_job_status');
    }

    /**
     * Gets the status values to filter by.
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * Sets the status values to filter by.
     */
    public function setValues(array $values): void
    {
        $this->values = $values;
    }

    /**
     * Gets the operator to use.
     */
    public function getOperator(): ?string
    {
        return $this->operator;
    }

    /**
     * Sets the operator to use.
     */
    public function setOperator(?string $operator): void
    {
        $this->operator = $operator;
    }

    public function isEnabled(): bool
    {
        return !empty($this->values) && $this->operator !== null;
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        // Do not apply a filter if values are empty or operator is null
        if (!$this->isEnabled()) {
            return;
        }

        // Use EXISTS subquery to check if part has a job with the specified status(es)
        $existsSubquery = $queryBuilder->getEntityManager()->createQueryBuilder();
        $existsSubquery->select('1')
            ->from(BulkInfoProviderImportJobPart::class, 'bip_status')
            ->join('bip_status.job', 'job_status')
            ->where('bip_status.part = part.id');

        // Add status conditions based on operator
        if ($this->operator === 'ANY') {
            $existsSubquery->andWhere('job_status.status IN (:job_status_values)');
            $queryBuilder->andWhere('EXISTS (' . $existsSubquery->getDQL() . ')');
            $queryBuilder->setParameter('job_status_values', $this->values);
        } elseif ($this->operator === 'NONE') {
            $existsSubquery->andWhere('job_status.status IN (:job_status_values)');
            $queryBuilder->andWhere('NOT EXISTS (' . $existsSubquery->getDQL() . ')');
            $queryBuilder->setParameter('job_status_values', $this->values);
        }
    }
}