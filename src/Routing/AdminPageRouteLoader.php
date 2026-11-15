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

namespace App\Routing;

use App\Controller\AdminPages\AdminPageController;
use App\Services\AdminPages\AdminCrudRegistry;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Generates admin CRUD routes from #[AdminPageEntity] attributes.
 * Referenced in config/routes/attributes.yaml as type "admin_pages".
 *
 * For each entity with #[AdminPageEntity(routeBase: 'foo')], these named routes are created:
 *   foo_new, foo_clone, foo_edit, foo_delete, foo_export_all, foo_export
 */
#[AutoconfigureTag('routing.loader')]
final class AdminPageRouteLoader extends Loader
{
    private bool $loaded = false;

    public function __construct(
        private readonly AdminCrudRegistry $registry,
        ?string $env = null,
    ) {
        parent::__construct($env);
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        if ($this->loaded) {
            throw new \RuntimeException('Do not add the "admin_pages" route loader twice.');
        }
        $this->loaded = true;

        $collection = new RouteCollection();
        $ctrl = AdminPageController::class;

        foreach ($this->registry->getAllConfigs() as $class => $config) {
            $base = $config->getRouteBase();
            $url = '/'.$base;
            $defaults = ['entityClass' => $class];

            // new / list page
            $collection->add($base.'_new', new Route(
                $url.'/new',
                array_merge($defaults, ['_controller' => $ctrl.'::new', 'id' => null]),
                [],
                [],
                '',
                [],
                ['GET', 'POST']
            ));

            // clone (same action as new, with a source id)
            $collection->add($base.'_clone', new Route(
                $url.'/{id}/clone',
                array_merge($defaults, ['_controller' => $ctrl.'::new']),
                ['id' => '\d+'],
                [],
                '',
                [],
                ['GET', 'POST']
            ));

            // unnamed root alias for new
            $collection->add('_'.$base.'_root', new Route(
                $url.'/',
                array_merge($defaults, ['_controller' => $ctrl.'::new', 'id' => null]),
                [],
                [],
                '',
                [],
                ['GET', 'POST']
            ));

            // export all
            $collection->add($base.'_export_all', new Route(
                $url.'/export',
                array_merge($defaults, ['_controller' => $ctrl.'::exportAll']),
                [],
                [],
                '',
                [],
                ['GET', 'POST']
            ));

            // edit (named, with optional timestamp)
            $collection->add($base.'_edit', new Route(
                $url.'/{id}/edit/{timestamp}',
                array_merge($defaults, ['_controller' => $ctrl.'::edit', 'timestamp' => null]),
                ['id' => '\d+'],
                [],
                '',
                [],
                ['GET', 'POST']
            ));

            // export single entity
            $collection->add($base.'_export', new Route(
                $url.'/{id}/export',
                array_merge($defaults, ['_controller' => $ctrl.'::exportEntity']),
                ['id' => '\d+'],
                [],
                '',
                [],
                ['GET', 'POST']
            ));

            // unnamed edit alias (id only, e.g. /category/5, instead of /category/5/edit)
            $collection->add('_'.$base.'_edit_alias', new Route(
                $url.'/{id}',
                array_merge($defaults, ['_controller' => $ctrl.'::edit', 'timestamp' => null]),
                ['id' => '\d+'],
                [],
                '',
                [],
                ['GET', 'POST']
            ));

            // delete
            $collection->add($base.'_delete', new Route(
                $url.'/{id}',
                array_merge($defaults, ['_controller' => $ctrl.'::delete']),
                ['id' => '\d+'],
                [],
                '',
                [],
                ['DELETE']
            ));
        }

        return $collection;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === 'admin_pages';
    }
}
