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

namespace App\Controller\AdminPages;

use App\Services\AdminPages\AdminCrudRegistry;
use App\Services\AdminPages\AdminPageExtensionInterface;
use App\Services\AdminPages\AdminPageHandler;
use App\Services\Trees\StructuralElementRecursionHelper;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Single generic controller for all admin CRUD pages.
 * Routes are generated automatically by AdminPageRouteLoader from #[AdminPageEntity] attributes.
 * Entity-specific behaviour lives in AdminPageExtensionInterface implementations declared via #[AdminCrud(extensionClass:)].
 */
final class AdminPageController extends AbstractController
{
    public function __construct(
        private readonly AdminPageHandler $handler,
        private readonly AdminCrudRegistry $adminCrudRegistry,
        #[TaggedLocator(AdminPageExtensionInterface::class)]
        private readonly ContainerInterface $extensions,
        private readonly StructuralElementRecursionHelper $recursionHelper,
    ) {
    }

    public function edit(string $entityClass, int $id, Request $request, ?string $timestamp = null): Response
    {
        return $this->handler->handleEdit($entityClass, $id, $request, $this->ext($entityClass), $timestamp);
    }

    public function new(string $entityClass, Request $request, ?int $id = null): Response
    {
        return $this->handler->handleNew($entityClass, $id, $request, $this->ext($entityClass));
    }

    public function delete(string $entityClass, int $id, Request $request): Response
    {
        return $this->handler->handleDelete($entityClass, $id, $request, $this->ext($entityClass), $this->recursionHelper);
    }

    public function exportAll(string $entityClass, Request $request): Response
    {
        return $this->handler->handleExportAll($entityClass, $request);
    }

    public function exportEntity(string $entityClass, int $id, Request $request): Response
    {
        return $this->handler->handleExportEntity($entityClass, $id, $request);
    }

    private function ext(string $entityClass): ?AdminPageExtensionInterface
    {
        if (!$this->adminCrudRegistry->hasConfig($entityClass)) {
            return null;
        }

        $extensionClass = $this->adminCrudRegistry->getConfig($entityClass)->getExtensionClass();
        if ($extensionClass === null) {
            return null;
        }

        return $this->extensions->has($extensionClass)
            ? $this->extensions->get($extensionClass)
            : null;
    }
}
