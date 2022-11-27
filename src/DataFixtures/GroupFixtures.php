<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\DataFixtures;

use App\Entity\UserSystem\Group;
use App\Services\UserSystem\PermissionPresetsHelper;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class GroupFixtures extends Fixture
{
    public const ADMINS = 'group-admin';
    public const USERS = 'group-users';
    public const READONLY = 'group-readonly';


    private PermissionPresetsHelper $permission_presets;

    public function __construct(PermissionPresetsHelper $permissionPresetsHelper)
    {
        $this->permission_presets = $permissionPresetsHelper;
    }

    public function load(ObjectManager $manager): void
    {
        $admins = new Group();
        $admins->setName('admins');
        //Set permissions using preset
        $this->permission_presets->applyPreset($admins, PermissionPresetsHelper::PRESET_ALL_ALLOW);
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
        $this->setReference(self::USERS, $users);
        $manager->persist($users);

        $manager->flush();
    }
}
