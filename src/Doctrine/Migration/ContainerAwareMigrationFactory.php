<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2025 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Doctrine\Migration;

use App\Services\UserSystem\PermissionPresetsHelper;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Version\MigrationFactory;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[AsDecorator("doctrine.migrations.migrations_factory")]
class ContainerAwareMigrationFactory implements MigrationFactory
{
    public function __construct(private readonly MigrationFactory $decorated,
        //List all services that should be available in migrations here
        #[AutowireLocator([
            PermissionPresetsHelper::class
        ])]
        private readonly ContainerInterface $container)
    {
    }

    public function createVersion(string $migrationClassName): AbstractMigration
    {
        $migration = $this->decorated->createVersion($migrationClassName);

        if ($migration instanceof ContainerAwareMigrationInterface) {
            $migration->setContainer($this->container);
        }

        return $migration;
    }
}