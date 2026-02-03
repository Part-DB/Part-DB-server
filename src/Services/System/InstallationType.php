<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

declare(strict_types=1);

namespace App\Services\System;

/**
 * Detects the installation type of Part-DB to determine the appropriate update strategy.
 */
enum InstallationType: string
{
    case GIT = 'git';
    case DOCKER = 'docker';
    case ZIP_RELEASE = 'zip_release';
    case UNKNOWN = 'unknown';

    public function getLabel(): string
    {
        return match ($this) {
            self::GIT => 'Git Clone',
            self::DOCKER => 'Docker',
            self::ZIP_RELEASE => 'Release Archive (ZIP File)',
            self::UNKNOWN => 'Unknown',
        };
    }

    public function supportsAutoUpdate(): bool
    {
        return match ($this) {
            self::GIT => true,
            self::DOCKER => false,
            // ZIP_RELEASE auto-update not yet implemented
            self::ZIP_RELEASE => false,
            self::UNKNOWN => false,
        };
    }

    public function getUpdateInstructions(): string
    {
        return match ($this) {
            self::GIT => 'Run: php bin/console partdb:update',
            self::DOCKER => 'Pull the new Docker image and recreate the container: docker-compose pull && docker-compose up -d',
            self::ZIP_RELEASE => 'Download the new release ZIP from GitHub, extract it over your installation, and run: php bin/console doctrine:migrations:migrate && php bin/console cache:clear',
            self::UNKNOWN => 'Unable to determine installation type. Please update manually.',
        };
    }
}
