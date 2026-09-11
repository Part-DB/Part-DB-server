<?php

declare(strict_types=1);

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

namespace App\Tests\Services\UserSystem;

use App\Entity\UserSystem\User;
use App\Services\UserSystem\PermissionManager;
use App\Services\UserSystem\PermissionPresetsHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PermissionPresetsHelperTest extends WebTestCase
{
    private static PermissionPresetsHelper $service;
    private static PermissionManager $permissionManager;

    public static function setUpBeforeClass(): void
    {
        self::bootKernel();
        self::$service = self::getContainer()->get(PermissionPresetsHelper::class);
        self::$permissionManager = self::getContainer()->get(PermissionManager::class);
    }

    private function createUser(): User
    {
        return new User();
    }

    public function testAllInheritPresetLeavesAllPermissionsInherit(): void
    {
        $user = $this->createUser();
        self::$service->applyPreset($user, PermissionPresetsHelper::PRESET_ALL_INHERIT);

        // After all-inherit preset, 'parts' read should be null (inherit)
        $this->assertNull(self::$permissionManager->dontInherit($user, 'parts', 'read'));
    }

    public function testAllForbidPresetSetsAllPermissionsToFalse(): void
    {
        $user = $this->createUser();
        self::$service->applyPreset($user, PermissionPresetsHelper::PRESET_ALL_FORBID);

        // After all-forbid, 'parts' read should be false (disallowed)
        $this->assertFalse(self::$permissionManager->dontInherit($user, 'parts', 'read'));
    }

    public function testAllAllowPresetSetsAllPermissionsToTrue(): void
    {
        $user = $this->createUser();
        self::$service->applyPreset($user, PermissionPresetsHelper::PRESET_ALL_ALLOW);

        // After all-allow, 'parts' read should be true (allowed)
        $this->assertTrue(self::$permissionManager->dontInherit($user, 'parts', 'read'));
    }

    public function testReadOnlyPresetAllowsPartsRead(): void
    {
        $user = $this->createUser();
        self::$service->applyPreset($user, PermissionPresetsHelper::PRESET_READ_ONLY);

        $this->assertTrue(self::$permissionManager->dontInherit($user, 'parts', 'read'));
    }

    public function testReadOnlyPresetDoesNotAllowPartsCreate(): void
    {
        $user = $this->createUser();
        self::$service->applyPreset($user, PermissionPresetsHelper::PRESET_READ_ONLY);

        // create should remain null (inherit) or false — not explicitly allowed
        $createValue = self::$permissionManager->dontInherit($user, 'parts', 'create');
        $this->assertNotTrue($createValue);
    }

    public function testUnknownPresetThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        self::$service->applyPreset($this->createUser(), 'non_existent_preset');
    }

    public function testApplyPresetReturnsTheSameUser(): void
    {
        $user = $this->createUser();
        $returned = self::$service->applyPreset($user, PermissionPresetsHelper::PRESET_ALL_INHERIT);
        $this->assertSame($user, $returned);
    }
}
