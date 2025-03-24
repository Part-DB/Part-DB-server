<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

class PermissionPresetsHelper
{
    final public const PRESET_ALL_INHERIT = 'all_inherit';
    final public const PRESET_ALL_FORBID = 'all_forbid';
    final public const PRESET_ALL_ALLOW = 'all_allow';
    final public const PRESET_READ_ONLY = 'read_only';
    final public const PRESET_EDITOR = 'editor';
    final public const PRESET_ADMIN = 'admin';

    public function __construct(private readonly PermissionManager $permissionResolver)
    {
    }

    /**
     * Apply the given preset to the permission holding entity (like a user)
     * The permission data will be reset during the process and then the preset will be applied.
     *
     * @param  string  $preset_name The name of the preset to use
     */
    public function applyPreset(HasPermissionsInterface $perm_holder, string $preset_name): HasPermissionsInterface
    {
        //We need to reset the permission data first (afterward all values are inherit)
        $perm_holder->getPermissions()->resetPermissions();

        switch($preset_name) {
            case self::PRESET_ALL_INHERIT:
                //Do nothing, all values are inherit after reset
                break;
            case self::PRESET_ALL_FORBID:
                $this->allForbid($perm_holder);
                break;
            case self::PRESET_ALL_ALLOW:
                $this->allAllow($perm_holder);
                break;
            case self::PRESET_READ_ONLY:
                $this->readOnly($perm_holder);
                break;
            case self::PRESET_EDITOR:
                $this->editor($perm_holder);
                break;
            case self::PRESET_ADMIN:
                $this->admin($perm_holder);
                break;

            default:
                throw new \InvalidArgumentException('Unknown permission preset name: '.$preset_name);
        }

        //Ensure that permissions are valid (alsoSet values are set), this allows us to use the permission inheritance system to keep the presets short
        $this->permissionResolver->ensureCorrectSetOperations($perm_holder);

        return $perm_holder;
    }

    private function admin(HasPermissionsInterface $perm_holder): void
    {
       //Apply everything from editor permission
        $this->editor($perm_holder);

        //Allow user and group access
        $this->permissionResolver->setAllOperationsOfPermission($perm_holder, 'users', PermissionData::ALLOW);
        $this->permissionResolver->setAllOperationsOfPermission($perm_holder, 'groups', PermissionData::ALLOW);

        //Allow access to system log and server infos
        $this->permissionResolver->setPermission($perm_holder, 'system', 'show_logs', PermissionData::ALLOW);
        $this->permissionResolver->setPermission($perm_holder, 'system', 'server_infos', PermissionData::ALLOW);

        //Allow import for all datastructures
        $this->permissionResolver->setAllOperationsOfPermission($perm_holder, 'parts', PermissionData::ALLOW);
        $this->permissionResolver->setAllOperationsOfPermission($perm_holder, 'parts_stock', PermissionData::ALLOW);
        $this->permissionResolver->setAllOperationsOfPermission($perm_holder, 'categories', PermissionData::ALLOW);
        $this->permissionResolver->setAllOperationsOfPermission($perm_holder, 'storelocations', PermissionData::ALLOW);
        $this->permissionResolver->setAllOperationsOfPermission($perm_holder, 'footprints', PermissionData::ALLOW);
        $this->permissionResolver->setAllOperationsOfPermission($perm_holder, 'manufacturers', PermissionData::ALLOW);
        $this->permissionResolver->setAllOperationsOfPermission($perm_holder, 'attachment_types', PermissionData::ALLOW);
        $this->permissionResolver->setAllOperationsOfPermission($perm_holder, 'currencies', PermissionData::ALLOW);
        $this->permissionResolver->setAllOperationsOfPermission($perm_holder, 'measurement_units', PermissionData::ALLOW);
        $this->permissionResolver->setAllOperationsOfPermission($perm_holder, 'part_custom_states', PermissionData::ALLOW);
        $this->permissionResolver->setAllOperationsOfPermission($perm_holder, 'suppliers', PermissionData::ALLOW);
        $this->permissionResolver->setAllOperationsOfPermission($perm_holder, 'projects', PermissionData::ALLOW);

        //Allow to change system settings
        $this->permissionResolver->setPermission($perm_holder, 'config', 'change_system_settings', PermissionData::ALLOW);

        //Allow to manage Oauth tokens
        $this->permissionResolver->setPermission($perm_holder, 'system', 'manage_oauth_tokens', PermissionData::ALLOW);
        //Allow to show updates
        $this->permissionResolver->setPermission($perm_holder, 'system', 'show_updates', PermissionData::ALLOW);

    }

    private function editor(HasPermissionsInterface $permHolder): HasPermissionsInterface
    {
        //Apply everything from read-only
        $this->readOnly($permHolder);

        //Set datastructures
        //By default import is restricted to administrators, as it allows to fill up the database very fast
        $this->permissionResolver->setAllOperationsOfPermissionExcept($permHolder, 'parts', PermissionData::ALLOW, ['import']);
        $this->permissionResolver->setAllOperationsOfPermission($permHolder, 'parts_stock', PermissionData::ALLOW);
        $this->permissionResolver->setAllOperationsOfPermissionExcept($permHolder, 'categories', PermissionData::ALLOW, ['import']);
        $this->permissionResolver->setAllOperationsOfPermissionExcept($permHolder, 'storelocations', PermissionData::ALLOW, ['import']);
        $this->permissionResolver->setAllOperationsOfPermissionExcept($permHolder, 'footprints', PermissionData::ALLOW, ['import']);
        $this->permissionResolver->setAllOperationsOfPermissionExcept($permHolder, 'manufacturers', PermissionData::ALLOW, ['import']);
        $this->permissionResolver->setAllOperationsOfPermissionExcept($permHolder, 'attachment_types', PermissionData::ALLOW, ['import']);
        $this->permissionResolver->setAllOperationsOfPermissionExcept($permHolder, 'currencies', PermissionData::ALLOW, ['import']);
        $this->permissionResolver->setAllOperationsOfPermissionExcept($permHolder, 'measurement_units', PermissionData::ALLOW, ['import']);
        $this->permissionResolver->setAllOperationsOfPermissionExcept($permHolder, 'suppliers', PermissionData::ALLOW, ['import']);
        $this->permissionResolver->setAllOperationsOfPermissionExcept($permHolder, 'projects', PermissionData::ALLOW, ['import']);

        //Attachments permissions
        $this->permissionResolver->setPermission($permHolder, 'attachments', 'show_private', PermissionData::ALLOW);

        //Labels permissions (allow all except use twig)
        $this->permissionResolver->setAllOperationsOfPermission($permHolder, 'labels', PermissionData::ALLOW);
        $this->permissionResolver->setPermission($permHolder,'labels', 'use_twig', PermissionData::INHERIT);

        //Self permissions
        $this->permissionResolver->setPermission($permHolder, 'self', 'edit_infos', PermissionData::ALLOW);

        //Various other permissions
        $this->permissionResolver->setPermission($permHolder, 'tools', 'lastActivity', PermissionData::ALLOW);

        //Allow to create parts from information providers
        $this->permissionResolver->setPermission($permHolder, 'info_providers', 'create_parts', PermissionData::ALLOW);


        return $permHolder;
    }

    private function readOnly(HasPermissionsInterface $perm_holder): HasPermissionsInterface
    {
        //It is sufficient to only set the read operation to allow, read operations for datastructures are inherited
        $this->permissionResolver->setPermission($perm_holder, 'parts', 'read', PermissionData::ALLOW);

        //Set tools permissions
        $this->permissionResolver->setPermission($perm_holder, 'tools', 'statistics', PermissionData::ALLOW);
        $this->permissionResolver->setPermission($perm_holder, 'tools', 'label_scanner', PermissionData::ALLOW);
        $this->permissionResolver->setPermission($perm_holder, 'tools', 'reel_calculator', PermissionData::ALLOW);
        $this->permissionResolver->setPermission($perm_holder, 'tools', 'builtin_footprints_viewer', PermissionData::ALLOW);
        $this->permissionResolver->setPermission($perm_holder, 'tools', 'ic_logos', PermissionData::ALLOW);

        //Set attachments permissions
        $this->permissionResolver->setPermission($perm_holder, 'attachments', 'list_attachments', PermissionData::ALLOW);

        //Set user (self) permissions
        $this->permissionResolver->setPermission($perm_holder, 'self', 'show_permissions', PermissionData::ALLOW);

        //Label permissions
        $this->permissionResolver->setPermission($perm_holder, 'labels', 'create_labels', PermissionData::ALLOW);
        $this->permissionResolver->setPermission($perm_holder, 'labels', 'edit_options', PermissionData::ALLOW);
        $this->permissionResolver->setPermission($perm_holder, 'labels', 'read_profiles', PermissionData::ALLOW);

        //Set projects permissions
        $this->permissionResolver->setPermission($perm_holder, 'projects', 'read', PermissionData::ALLOW);

        return $perm_holder;
    }

    private function allForbid(HasPermissionsInterface $perm_holder): HasPermissionsInterface
    {
        $this->permissionResolver->setAllPermissions($perm_holder, PermissionData::DISALLOW);
        return $perm_holder;
    }

    private function allAllow(HasPermissionsInterface $perm_holder): HasPermissionsInterface
    {
        $this->permissionResolver->setAllPermissions($perm_holder, PermissionData::ALLOW);
        return $perm_holder;
    }
}
