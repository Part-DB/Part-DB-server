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

use App\Entity\Attribute\AdminPageEntity;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Discovers all Doctrine entity classes annotated with #[AdminCrud] and provides access to their config.
 *
 * Discovery is cached in the system cache pool (cleared with cache:clear) and in memory within
 * a single request, so reflection runs at most once per request and rarely across requests.
 *
 * Attachment and parameter classes are detected from the Doctrine association metadata
 * ('attachments' and 'parameters' field names) rather than being declared in the attribute.
 */
class AdminPageEntityRegistry
{
    private const CACHE_KEY = 'admin_crud_entity_classes';

    /** @var array<class-string, AdminPageEntityConfig>|null */
    private ?array $configs = null;

    public function __construct(
        private readonly ManagerRegistry $doctrine,
        #[Autowire(service: 'cache.system')]
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * @param class-string $entityClass
     * @throws \InvalidArgumentException if the entity has no #[AdminCrud] attribute
     */
    public function getConfig(string $entityClass): AdminPageEntityConfig
    {
        $all = $this->getAllConfigs();

        return $all[$entityClass]
            ?? throw new \InvalidArgumentException(sprintf(
                'Entity class "%s" has no #[AdminCrud] attribute.',
                $entityClass
            ));
    }

    public function hasConfig(string $entityClass): bool
    {
        return isset($this->getAllConfigs()[$entityClass]);
    }

    /**
     * @return array<class-string, AdminPageEntityConfig>
     */
    public function getAllConfigs(): array
    {
        if ($this->configs !== null) {
            return $this->configs;
        }

        /** @var string[] $entityClasses */
        $entityClasses = $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): array {
            return $this->discoverEntityClasses();
        });

        $em = $this->doctrine->getManager();
        $this->configs = [];
        foreach ($entityClasses as $class) {
            /** @var ClassMetadata $metadata */
            $metadata = $em->getMetadataFactory()->getMetadataFor($class);
            $this->configs[$class] = $this->buildConfig($class, $metadata);
        }

        return $this->configs;
    }

    private function buildConfig(string $class, ClassMetadata $metadata): AdminPageEntityConfig
    {
        $ref = new \ReflectionClass($class);
        $attrs = $ref->getAttributes(AdminPageEntity::class);
        /** @var AdminPageEntity $attr */
        $attr = $attrs[0]->newInstance();

        $attachmentClass = $metadata->hasAssociation('attachments')
            ? $metadata->getAssociationTargetClass('attachments')
            : null;

        $parameterClass = $metadata->hasAssociation('parameters')
            ? $metadata->getAssociationTargetClass('parameters')
            : null;

        return new AdminPageEntityConfig($attr, $class, $attachmentClass, $parameterClass);
    }

    /**
     * @return string[]
     */
    private function discoverEntityClasses(): array
    {
        $classes = [];
        $em = $this->doctrine->getManager();

        foreach ($em->getMetadataFactory()->getAllMetadata() as $metadata) {
            /** @var class-string $class */
            $class = $metadata->getName();

            if (!empty((new \ReflectionClass($class))->getAttributes(AdminPageEntity::class))) {
                $classes[] = $class;
            }
        }

        return $classes;
    }
}
