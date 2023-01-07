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

use App\Entity\UserSystem\PermissionData;
use App\Security\Interfaces\HasPermissionsInterface;

class PermissionSchemaUpdater
{
    /**
     * Check if the given user/group needs an update of its permission schema.
     * @param  HasPermissionsInterface  $holder
     * @return bool True if the permission schema needs an update, false otherwise.
     */
    public function isSchemaUpdateNeeded(HasPermissionsInterface $holder): bool
    {
        $perm_data = $holder->getPermissions();

        if ($perm_data->getSchemaVersion() < PermissionData::CURRENT_SCHEMA_VERSION) {
            return true;
        }

        return false;
    }

    /**
     * Upgrades the permission schema of the given user/group to the chosen version
     * @param  HasPermissionsInterface  $holder
     * @param  int  $target_version
     * @return bool True, if an upgrade was done, false if it was not needed.
     */
    public function upgradeSchema(HasPermissionsInterface $holder, int $target_version = PermissionData::CURRENT_SCHEMA_VERSION): bool
    {
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
                $method->invoke($this, $holder);
            } catch (\ReflectionException $e) {
                throw new \RuntimeException('Could not find update method for schema version '.($n + 1));
            }

            //Bump the schema version
            $holder->getPermissions()->setSchemaVersion($n + 1);
        }

        //When we end up here, we have done an upgrade and we can return true
        return true;
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
}