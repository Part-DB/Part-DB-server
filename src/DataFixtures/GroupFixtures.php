<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\DataFixtures;

use App\Entity\UserSystem\Group;
use App\Services\UserSystem\PermissionManager;
use App\Services\UserSystem\PermissionPresetsHelper;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class GroupFixtures extends Fixture
{
    final public const ADMINS = 'group-admin';
    final public const USERS = 'group-users';
    final public const READONLY = 'group-readonly';

    public function __construct(private readonly PermissionPresetsHelper $permission_presets, private readonly PermissionManager $permissionManager)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $admins = new Group();
        $admins->setName('admins');
        //Set permissions using preset
        $this->permission_presets->applyPreset($admins, PermissionPresetsHelper::PRESET_ALL_ALLOW);
        $this->addDevicesPermissions($admins);
        $this->setReference(self::ADMINS, $admins);
        $manager->persist($admins);

        $readonly = new Group();
        $readonly->setName('readonly');
        $this->permission_presets->applyPreset($readonly, PermissionPresetsHelper::PRESET_READ_ONLY);
        $this->setReference(self::READONLY, $readonly);
        $manager->persist($readonly);

        $users = new Group();
        $users->setName('users');
        $this->permission_presets->applyPreset($users, PermissionPresetsHelper::PRESET_EDITOR);
        $this->addDevicesPermissions($users);
        $this->addAssemblyPermissions($users);
        $this->setReference(self::USERS, $users);
        $manager->persist($users);

        $manager->flush();
    }

    private function addDevicesPermissions(Group $group): void
    {
        $this->permissionManager->setAllOperationsOfPermission($group, 'projects', true);
    }

    private function addAssemblyPermissions(Group $group): void
    {
        $this->permissionManager->setAllOperationsOfPermission($group, 'assemblies', true);
    }

}
