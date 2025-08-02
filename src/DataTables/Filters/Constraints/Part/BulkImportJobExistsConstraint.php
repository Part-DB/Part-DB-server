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

class BulkImportJobExistsConstraint extends AbstractConstraint
{
    /** @var bool|null The value of our constraint */
    protected ?bool $value = null;

    public function __construct()
    {
        parent::__construct('bulk_import_job_exists');
    }

    /**
     * Gets the value of this constraint. Null means "don't filter", true means "filter for parts in bulk import jobs", false means "filter for parts not in bulk import jobs".
     */
    public function getValue(): ?bool
    {
        return $this->value;
    }

    /**
     * Sets the value of this constraint. Null means "don't filter", true means "filter for parts in bulk import jobs", false means "filter for parts not in bulk import jobs".
     */
    public function setValue(?bool $value): void
    {
        $this->value = $value;
    }

    public function isEnabled(): bool
    {
        return $this->value !== null;
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        // Do not apply a filter if value is null (filter is set to ignore)
        if (!$this->isEnabled()) {
            return;
        }

        // Use EXISTS subquery to avoid join conflicts
        $existsSubquery = $queryBuilder->getEntityManager()->createQueryBuilder();
        $existsSubquery->select('1')
            ->from(BulkInfoProviderImportJobPart::class, 'bip_exists')
            ->where('bip_exists.part = part.id');

        if ($this->value === true) {
            // Filter for parts that ARE in bulk import jobs
            $queryBuilder->andWhere('EXISTS (' . $existsSubquery->getDQL() . ')');
        } else {
            // Filter for parts that are NOT in bulk import jobs
            $queryBuilder->andWhere('NOT EXISTS (' . $existsSubquery->getDQL() . ')');
        }
    }
}