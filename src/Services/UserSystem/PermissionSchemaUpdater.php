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

namespace App\Services\UserSystem;

use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\PermissionData;
use App\Entity\UserSystem\User;
use App\Security\Interfaces\HasPermissionsInterface;

class PermissionSchemaUpdater
{
    /**
     * Check if the given user/group needs an update of its permission schema.
     * @return bool True if the permission schema needs an update, false otherwise.
     */
    public function isSchemaUpdateNeeded(HasPermissionsInterface $holder): bool
    {
        $perm_data = $holder->getPermissions();

        return $perm_data->getSchemaVersion() < PermissionData::CURRENT_SCHEMA_VERSION;
    }

    /**
     * Upgrades the permission schema of the given user/group to the chosen version.
     * Please note that this function does not flush the changes to DB!
     * @return bool True, if an upgrade was done, false if it was not needed.
     */
    public function upgradeSchema(HasPermissionsInterface $holder, int $target_version = PermissionData::CURRENT_SCHEMA_VERSION): bool
    {
        $e = null;
        if ($target_version > PermissionData::CURRENT_SCHEMA_VERSION) {
            throw new \InvalidArgumentException('The target version is higher than the maximum possible schema version!');
        }

        //Check if we need to do an update, if not, return false
        if ($target_version <= $holder->getPermissions()->getSchemaVersion()) {
            return false;
        }

        //Do the update
        for ($n = $holder->getPermissions()->getSchemaVersion(); $n < $target_version; ++$n) {
            $reflectionClass = new \ReflectionClass(self::class);
            try {
                $method = $reflectionClass->getMethod('upgradeSchemaToVersion'.($n + 1));
                //Set the method accessible, so we can call it (needed for PHP < 8.1)
                $method->setAccessible(true);
                $method->invoke($this, $holder);
            } catch (\ReflectionException $e) {
                throw new \RuntimeException('Could not find update method for schema version '.($n + 1), $e->getCode(), $e);
            }

            //Bump the schema version
            $holder->getPermissions()->setSchemaVersion($n + 1);
        }

        //When we end up here, we have done an upgrade and we can return true
        return true;
    }

    /**
     * Upgrades the permission schema of the given group and all of its parent groups to the chosen version.
     * Please note that this function does not flush the changes to DB!
     * @return bool True if an upgrade was done, false if it was not needed.
     */
    public function groupUpgradeSchemaRecursively(Group $group, int $target_version = PermissionData::CURRENT_SCHEMA_VERSION): bool
    {
        $updated = $this->upgradeSchema($group, $target_version);

        /** @var Group $parent */
        $parent = $group->getParent();
        while ($parent) {
            $updated = $this->upgradeSchema($parent, $target_version) || $updated;
            $parent = $parent->getParent();
        }

        return $updated;
    }

    /**
     * Upgrades the permissions schema of the given users and its parent (including parent groups) to the chosen version.
     * Please note that this function does not flush the changes to DB!
     * @return bool True if an upgrade was done, false if it was not needed.
     */
    public function userUpgradeSchemaRecursively(User $user, int $target_version = PermissionData::CURRENT_SCHEMA_VERSION): bool
    {
        $updated = $this->upgradeSchema($user, $target_version);
        if ($user->getGroup() instanceof Group) {
            $updated = $this->groupUpgradeSchemaRecursively($user->getGroup(), $target_version) || $updated;
        }

        return $updated;
    }

    private function upgradeSchemaToVersion1(HasPermissionsInterface $holder): void
    {
        //Use the part edit permission to set the preset value for the new part stock permission
        if (
            !$holder->getPermissions()->isPermissionSet('parts_stock', 'withdraw')
            && !$holder->getPermissions()->isPermissionSet('parts_stock', 'add')
            && !$holder->getPermissions()->isPermissionSet('parts_stock', 'move')
        ) { //Only do migration if the permission was not set already

            $new_value = $holder->getPermissions()->getPermissionValue('parts', 'edit');

            $holder->getPermissions()->setPermissionValue('parts_stock', 'withdraw', $new_value);
            $holder->getPermissions()->setPermissionValue('parts_stock', 'add', $new_value);
            $holder->getPermissions()->setPermissionValue('parts_stock', 'move', $new_value);
        }
    }

    private function upgradeSchemaToVersion2(HasPermissionsInterface $holder): void
    {
        //If the projects permissions are not defined yet, rename devices permission to projects (just copy its data over)
        if (!$holder->getPermissions()->isAnyOperationOfPermissionSet('projects')) {
            $operations_value = $holder->getPermissions()->getAllDefinedOperationsOfPermission('devices');
            $holder->getPermissions()->setAllOperationsOfPermission('projects', $operations_value);
            $holder->getPermissions()->removePermission('devices');
        }
    }
}