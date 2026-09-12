<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Tests\API\Endpoints;

use App\Entity\Parts\Category;
use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\PermissionData;
use App\Entity\UserSystem\User;
use App\Tests\API\Endpoints\CrudEndpointTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class CategoryEndpointTest extends CrudEndpointTestCase
{

    protected function getBasePath(): string
    {
        return '/api/categories';
    }

    public function testGetCollection(): void
    {
        $this->_testGetCollection();
        self::assertJsonContains([
            'hydra:totalItems' => 7,
        ]);
    }

    public function testGetChildrenCollection(): void
    {
        $this->_testGetChildrenCollection(1);
    }

    public function testGetItem(): void
    {
        $this->_testGetItem(1);
        $this->_testGetItem(2);
        $this->_testGetItem(3);
    }

    public function testCreateItem(): void
    {
        $this->_testPostItem([
            'name' => 'Test API',
            'parent' => '/api/categories/1',
        ]);
    }

    public function testCreateItemUpgradesLegacyPermissionSchema(): void
    {
        $client = self::createAuthenticatedClient();
        $entity_manager = self::getContainer()->get(EntityManagerInterface::class);
        $admin = $entity_manager->getRepository(User::class)->findOneBy(['name' => 'admin']);
        self::assertInstanceOf(User::class, $admin);
        $admin_group = $admin->getGroup();
        self::assertInstanceOf(Group::class, $admin_group);

        foreach ([$admin, $admin_group] as $permission_holder) {
            $permission_holder->getPermissions()
                ->removePermission('parameter_definitions')
                ->setSchemaVersion(4);
        }
        $entity_manager->flush();
        $entity_manager->clear();

        $response = $client->request('POST', '/api/categories', [
            'json' => [
                'name' => 'Permission schema upgrade API',
                'parent' => '/api/categories/1',
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseStatusCodeSame(201);
        $category_id = $this->getIdOfCreatedElement($response);

        $entity_manager = self::getContainer()->get(EntityManagerInterface::class);
        $entity_manager->clear();
        $created_category = $entity_manager->find(Category::class, $category_id);
        self::assertInstanceOf(Category::class, $created_category);
        self::assertSame('Permission schema upgrade API', $created_category->getName());

        $upgraded_admin = $entity_manager->getRepository(User::class)->findOneBy(['name' => 'admin']);
        self::assertInstanceOf(User::class, $upgraded_admin);
        $upgraded_group = $upgraded_admin->getGroup();
        self::assertInstanceOf(Group::class, $upgraded_group);

        foreach ([$upgraded_admin, $upgraded_group] as $permission_holder) {
            $permissions = $permission_holder->getPermissions();
            self::assertSame(PermissionData::CURRENT_SCHEMA_VERSION, $permissions->getSchemaVersion());
            self::assertSame(
                $permissions->getPermissionValue('parts', 'read'),
                $permissions->getPermissionValue('parameter_definitions', 'read'),
            );
            foreach (['edit', 'create', 'delete', 'show_history', 'revert_element', 'import'] as $operation) {
                self::assertSame(
                    $permissions->getPermissionValue('config', 'change_system_settings'),
                    $permissions->getPermissionValue('parameter_definitions', $operation),
                );
            }
        }
    }

    public function testUpdateItem(): void
    {
        $this->_testPatchItem(1, [
            'name' => 'Updated',
            'parent' => '/api/categories/2',
        ]);
    }

    public function testDeleteItem(): void
    {
        $this->_testDeleteItem(5);
    }
}
