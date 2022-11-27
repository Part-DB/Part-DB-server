<?php

namespace App\Services\UserSystem;

use App\Entity\UserSystem\PermissionData;
use App\Security\Interfaces\HasPermissionsInterface;

class PermissionPresetsHelper
{
    public const PRESET_ALL_INHERIT = 'all_inherit';
    public const PRESET_ALL_FORBID = 'all_forbid';
    public const PRESET_ALL_ALLOW = 'all_allow';
    public const PRESET_READ_ONLY = 'read_only';
    public const PRESET_EDITOR = 'editor';
    public const PRESET_ADMIN = 'admin';

    private PermissionManager $permissionResolver;

    public function __construct(PermissionManager $permissionResolver)
    {
        $this->permissionResolver = $permissionResolver;
    }

    /**
     * Apply the given preset to the permission holding entity (like a user)
     * The permission data will be reset during the process and then the preset will be applied.
     *
     * @param  string  $preset_name The name of the preset to use
     * @return HasPermissionsInterface
     */
    public function applyPreset(HasPermissionsInterface $perm_holder, string $preset_name): HasPermissionsInterface
    {
        //We need to reset the permission data first (afterwards all values are inherit)
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
    }

    private function editor(HasPermissionsInterface $permHolder): HasPermissionsInterface
    {
        //Apply everything from read-only
        $this->readOnly($permHolder);

        //Set datastructures
        $this->permissionResolver->setAllOperationsOfPermission($permHolder, 'parts', PermissionData::ALLOW);
        $this->permissionResolver->setAllOperationsOfPermission($permHolder, 'categories', PermissionData::ALLOW);
        $this->permissionResolver->setAllOperationsOfPermission($permHolder, 'storelocations', PermissionData::ALLOW);
        $this->permissionResolver->setAllOperationsOfPermission($permHolder, 'footprints', PermissionData::ALLOW);
        $this->permissionResolver->setAllOperationsOfPermission($permHolder, 'manufacturers', PermissionData::ALLOW);
        $this->permissionResolver->setAllOperationsOfPermission($permHolder, 'attachment_types', PermissionData::ALLOW);
        $this->permissionResolver->setAllOperationsOfPermission($permHolder, 'currencies', PermissionData::ALLOW);
        $this->permissionResolver->setAllOperationsOfPermission($permHolder, 'measurement_units', PermissionData::ALLOW);
        $this->permissionResolver->setAllOperationsOfPermission($permHolder, 'suppliers', PermissionData::ALLOW);

        //Attachments permissions
        $this->permissionResolver->setPermission($permHolder, 'attachments', 'show_private', PermissionData::ALLOW);

        //Labels permissions (allow all except use twig)
        $this->permissionResolver->setAllOperationsOfPermission($permHolder, 'labels', PermissionData::ALLOW);
        $this->permissionResolver->setPermission($permHolder,'labels', 'use_twig', PermissionData::INHERIT);

        //Self permissions
        $this->permissionResolver->setPermission($permHolder, 'self', 'edit_infos', PermissionData::ALLOW);

        //Various other permissions
        $this->permissionResolver->setPermission($permHolder, 'tools', 'lastActivity', PermissionData::ALLOW);


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

        //Set attachments permissions
        $this->permissionResolver->setPermission($perm_holder, 'attachments', 'list_attachments', PermissionData::ALLOW);

        //Set user (self) permissions
        $this->permissionResolver->setPermission($perm_holder, 'self', 'show_permissions', PermissionData::ALLOW);

        //Label permissions
        $this->permissionResolver->setPermission($perm_holder, 'labels', 'create_labels', PermissionData::ALLOW);
        $this->permissionResolver->setPermission($perm_holder, 'labels', 'edit_options', PermissionData::ALLOW);
        $this->permissionResolver->setPermission($perm_holder, 'labels', 'read_profiles', PermissionData::ALLOW);

        //Set devices permissions
        $this->permissionResolver->setPermission($perm_holder, 'devices', 'read', PermissionData::ALLOW);

        return $perm_holder;
    }

    private function AllForbid(HasPermissionsInterface $perm_holder): HasPermissionsInterface
    {
        $this->permissionResolver->setAllPermissions($perm_holder, PermissionData::DISALLOW);
        return $perm_holder;
    }

    private function AllAllow(HasPermissionsInterface $perm_holder): HasPermissionsInterface
    {
        $this->permissionResolver->setAllPermissions($perm_holder, PermissionData::ALLOW);
        return $perm_holder;
    }
}