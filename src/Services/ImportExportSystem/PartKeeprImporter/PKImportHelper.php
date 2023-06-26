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
namespace App\Services\ImportExportSystem\PartKeeprImporter;

use App\Doctrine\Purger\ResetAutoIncrementORMPurger;
use Doctrine\ORM\EntityManagerInterface;

/**
 * This service contains various helper functions for the PartKeeprImporter (like purging the database).
 */
class PKImportHelper
{
    public function __construct(protected EntityManagerInterface $em)
    {
    }

    /**
     * Purges the database tables for the import, so that all data can be created from scratch.
     * Existing users and groups are not purged.
     * This is needed to avoid ID collisions.
     */
    public function purgeDatabaseForImport(): void
    {
        //We use the ResetAutoIncrementORMPurger to reset the auto increment values of the tables. Also it normalizes table names before checking for exclusion.
        $purger = new ResetAutoIncrementORMPurger($this->em, ['users', 'groups', 'u2f_keys', 'internal', 'migration_versions']);
        $purger->purge();
    }

    /**
     * Extracts the current database schema version from the PartKeepr XML dump.
     */
    public function getDatabaseSchemaVersion(array $data): string
    {
        if (!isset($data['schemaversions'])) {
            throw new \RuntimeException('Could not find schema version in XML dump!');
        }

        return end($data['schemaversions'])['version'];
    }

    /**
     * Checks that the database schema of the PartKeepr XML dump is compatible with the importer
     * @return bool True if the schema is compatible, false otherwise
     */
    public function checkVersion(array $data): bool
    {
        return $this->getDatabaseSchemaVersion($data) === '20170601175559';
    }
}
