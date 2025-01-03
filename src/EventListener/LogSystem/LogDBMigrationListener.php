<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\EventListener\LogSystem;

use App\Entity\LogSystem\DatabaseUpdatedLogEntry;
use App\Services\LogSystem\EventLogger;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Event\MigrationsEventArgs;
use Doctrine\Migrations\Events;

/**
 * This subscriber logs databaseMigrations to Event log.
 */
#[AsDoctrineListener(event: Events::onMigrationsMigrated)]
#[AsDoctrineListener(event: Events::onMigrationsMigrating)]
class LogDBMigrationListener
{
    protected ?string $old_version = null;
    protected ?string $new_version = null;

    public function __construct(protected EventLogger $eventLogger, protected DependencyFactory $dependencyFactory)
    {
    }

    public function onMigrationsMigrated(MigrationsEventArgs $args): void
    {
        //Dont do anything if this was a dry run
        if ($args->getMigratorConfiguration()->isDryRun()) {
            return;
        }

        $aliasResolver = $this->dependencyFactory->getVersionAliasResolver();

        //Save the version after the migration
        //$this->new_version = $args->getMigratorConfiguration()->getCurrentVersion();
        $this->new_version = (string) $aliasResolver->resolveVersionAlias('current');

        //After everything is done, write the results to DB log
        $this->old_version = $this->old_version === null || $this->old_version === '' ? 'legacy/empty' : $this->old_version;
        $this->new_version = $this->new_version === '' ? 'unknown' : $this->new_version;

        try {
            $log = new DatabaseUpdatedLogEntry($this->old_version, $this->new_version);
            $this->eventLogger->logAndFlush($log);
        } catch (\Throwable) {
            //Ignore any exception occuring here...
        }
    }

    public function onMigrationsMigrating(MigrationsEventArgs $args): void
    {
        // Save the version before any migration
        if (null === $this->old_version) {
            $aliasResolver = $this->dependencyFactory->getVersionAliasResolver();

            //$this->old_version = $args->getConfiguration()->getCurrentVersion();
            $this->old_version = (string) $aliasResolver->resolveVersionAlias('current');
        }
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::onMigrationsMigrated,
            Events::onMigrationsMigrating,
        ];
    }
}
