<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\EventSubscriber\LogSystem;

use App\Entity\LogSystem\DatabaseUpdatedLogEntry;
use App\Services\LogSystem\EventLogger;
use Doctrine\Common\EventSubscriber;
use Doctrine\Migrations\Event\MigrationsEventArgs;
use Doctrine\Migrations\Events;

/**
 * This subscriber logs databaseMigrations to Event log.
 * @package App\EventSubscriber
 */
class LogDBMigrationSubscriber implements EventSubscriber
{
    protected $old_version = null;
    protected $new_version = null;

    protected $eventLogger;

    public function __construct(EventLogger $eventLogger)
    {
        $this->eventLogger = $eventLogger;
    }

    public function onMigrationsMigrated(MigrationsEventArgs $args): void
    {
        //Dont do anything if this was a dry run
        if ($args->isDryRun()) {
            return;
        }

        //Save the version after the migration
        $this->new_version = $args->getConfiguration()->getCurrentVersion();

        //After everything is done, write the results to DB log
        $this->old_version = empty($this->old_version) ? 'legacy/empty' : $this->old_version;
        $this->new_version = empty($this->new_version) ? 'unknown' : $this->new_version;

        try {
            $log = new DatabaseUpdatedLogEntry($this->old_version, $this->new_version);
            $this->eventLogger->logAndFlush($log);
        } catch (\Throwable $exception) {
            //Ignore any exception occuring here...
        }
    }

    public function onMigrationsMigrating(MigrationsEventArgs $args): void
    {
        // Save the version before any migration
        if (null === $this->old_version) {
            $this->old_version = $args->getConfiguration()->getCurrentVersion();
        }
    }

    public function getSubscribedEvents()
    {
        return [
            Events::onMigrationsMigrated,
            Events::onMigrationsMigrating,
        ];
    }
}
