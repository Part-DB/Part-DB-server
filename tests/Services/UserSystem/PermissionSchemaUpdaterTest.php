<?php
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

namespace App\Tests\Services\UserSystem;

use App\Entity\UserSystem\PermissionData;
use App\Security\Interfaces\HasPermissionsInterface;
use App\Services\UserSystem\PermissionSchemaUpdater;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TestPermissionHolder implements HasPermissionsInterface
{
    private PermissionData $perm_data;

    public function __construct(PermissionData $perm_data)
    {
        $this->perm_data = $perm_data;
    }

    public function getPermissions(): PermissionData
    {
        return $this->perm_data;
    }
}

class PermissionSchemaUpdaterTest extends WebTestCase
{
    /**
     * @var PermissionSchemaUpdater
     */
    protected $service;

    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->service = self::$container->get(PermissionSchemaUpdater::class);
    }

    public function testIsSchemaUpdateNeeded()
    {
        $perm_data = new PermissionData();
        $perm_data->setSchemaVersion(0);
        $user = new TestPermissionHolder($perm_data);

        //With schema version 0, an update should be needed
        self::assertTrue($this->service->isSchemaUpdateNeeded($user));

        //With a very high scheme number no update should be needed
        $perm_data->setSchemaVersion(123456);
        self::assertFalse($this->service->isSchemaUpdateNeeded($user));
    }

    public function testUpgradeSchema()
    {
        $perm_data = new PermissionData();
        $perm_data->setSchemaVersion(0);
        $user = new TestPermissionHolder($perm_data);

        //With schema version 0, an update should be done and the schema version should be updated
        self::assertTrue($this->service->upgradeSchema($user));
        self::assertEquals(PermissionData::CURRENT_SCHEMA_VERSION, $user->getPermissions()->getSchemaVersion());

        //If we redo it with the same schema version, no update should be done
        self::assertFalse($this->service->upgradeSchema($user));
    }

    public function testUpgradeSchemaToVersion1()
    {
        $perm_data = new PermissionData();
        $perm_data->setSchemaVersion(0);
        $perm_data->setPermissionValue('parts', 'edit', PermissionData::ALLOW);
        $user = new TestPermissionHolder($perm_data);

        //Do an upgrade and afterwards the move, add, and withdraw permissions should be set to ALLOW
        self::assertTrue($this->service->upgradeSchema($user, 1));
        self::assertEquals(PermissionData::ALLOW, $user->getPermissions()->getPermissionValue('parts_stock', 'move'));
        self::assertEquals(PermissionData::ALLOW, $user->getPermissions()->getPermissionValue('parts_stock', 'add'));
        self::assertEquals(PermissionData::ALLOW, $user->getPermissions()->getPermissionValue('parts_stock', 'withdraw'));
    }
}
