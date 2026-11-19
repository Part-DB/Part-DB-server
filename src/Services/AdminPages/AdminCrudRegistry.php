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

namespace App\Services\AdminPages;

use App\Entity\Attribute\AdminCrud;
use App\Services\EntitySystem\EntityConfigRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * Provides access to the admin CRUD configuration for entity classes annotated with both
 * #[EntityConfig] and #[AdminPageEntity].
 *
 * Relies on {@see EntityConfigRegistry} for generic entity metadata (route base,
 * attachment/parameter classes) and reads #[AdminPageEntity] itself for admin-specific fields.
 */
#[Autoconfigure(public: true)]
class AdminCrudRegistry
{
    /** @var array<class-string, AdminCrudConfig>|null */
    private ?array $configs = null;

    public function __construct(
        private readonly EntityConfigRegistry $entityConfigRegistry,
    ) {
    }

    /**
     * @param class-string $entityClass
     * @throws \InvalidArgumentException if the entity has no #[AdminPageEntity] attribute
     */
    public function getConfig(string $entityClass): AdminCrudConfig
    {
        return $this->getAllConfigs()[$entityClass]
            ?? throw new \InvalidArgumentException(sprintf(
                'Entity class "%s" has no #[AdminPageEntity] attribute.',
                $entityClass
            ));
    }

    public function hasConfig(string $entityClass): bool
    {
        return isset($this->getAllConfigs()[$entityClass]);
    }

    /**
     * @return array<class-string, AdminCrudConfig>
     */
    public function getAllConfigs(): array
    {
        if ($this->configs !== null) {
            return $this->configs;
        }

        $this->configs = [];
        foreach ($this->entityConfigRegistry->getAllConfigs() as $class => $entityConfigData) {
            $ref = new \ReflectionClass($class);
            $attrs = $ref->getAttributes(AdminCrud::class);
            if (empty($attrs)) {
                continue;
            }
            /** @var AdminCrud $attr */
            $attr = $attrs[0]->newInstance();
            $this->configs[$class] = new AdminCrudConfig($attr, $entityConfigData);
        }

        return $this->configs;
    }
}
