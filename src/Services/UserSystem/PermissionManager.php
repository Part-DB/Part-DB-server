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

namespace App\Services\UserSystem;

use App\Entity\Base\AbstractStructuralDBElement;
use App\Configuration\PermissionsConfiguration;
use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\User;
use App\Security\Interfaces\HasPermissionsInterface;
use InvalidArgumentException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Yaml\Yaml;

/**
 * This class manages the permissions of users and groups.
 * Permissions are defined in the config/permissions.yaml file, and are parsed and resolved by this class using the
 * user and hierachical group PermissionData information.
 * @see \App\Tests\Services\UserSystem\PermissionManagerTest
 */
class PermissionManager
{
    protected array $permission_structure;
    protected string $cache_file;

    /**
     * PermissionResolver constructor.
     */
    public function __construct(protected readonly bool $kernel_debug_enabled, string $kernel_cache_dir)
    {
        $cache_dir = $kernel_cache_dir;
        //Here the cached structure will be saved.
        $this->cache_file = $cache_dir.'/permissions.php.cache';

        $this->permission_structure = $this->generatePermissionStructure();
    }

    public function getPermissionStructure(): array
    {
        return $this->permission_structure;
    }

    /**
     * Check if a user/group is allowed to do the specified operation for the permission.
     *
     * See permissions.yaml for valid permission operation combinations.
     * This function does not check, if the permission is valid!
     *
     * @param HasPermissionsInterface $user       the user/group for which the operation should be checked
     * @param string                  $permission the name of the permission for which should be checked
     * @param string                  $operation  the name of the operation for which should be checked
     *
     * @return bool|null true, if the user is allowed to do the operation (ALLOW), false if not (DISALLOW), and null,
     *                   if the value is set to inherit
     */
    public function dontInherit(HasPermissionsInterface $user, string $permission, string $operation): ?bool
    {
        //Check that the permission/operation combination is valid
        if (! $this->isValidOperation($permission, $operation)) {
            throw new InvalidArgumentException('The permission/operation combination "'.$permission.'/'.$operation.'" is not valid!');
        }

        //Get the permissions from the user
        return $user->getPermissions()->getPermissionValue($permission, $operation);
    }

    /**
     * Checks if a user is allowed to do the specified operation for the permission.
     * In contrast to dontInherit() it tries to resolve to inherit values, of the user, by going upwards in the
     * hierarchy (user -> group -> parent group -> so on). But even in this case it is possible, that to inherit value
     * could be resolved, and this function returns null.
     *
     * In that case the voter should set it manually to false by using ?? false.
     *
     * @param User   $user       the user for which the operation should be checked
     * @param string $permission the name of the permission for which should be checked
     * @param string $operation  the name of the operation for which should be checked
     *
     * @return bool|null true, if the user is allowed to do the operation (ALLOW), false if not (DISALLOW), and null,
     *                   if the value is set to inherit
     */
    public function inherit(User $user, string $permission, string $operation): ?bool
    {
        //Check if we need to inherit
        $allowed = $this->dontInherit($user, $permission, $operation);

        if (null !== $allowed) {
            //Just return the value of the user.
            return $allowed;
        }

        /** @var Group $parent */
        $parent = $user->getGroup();
        while ($parent instanceof Group) { //The top group, has parent == null
            //Check if our current element gives an info about disallow/allow
            $allowed = $this->dontInherit($parent, $permission, $operation);
            if (null !== $allowed) {
                return $allowed;
            }
            //Else go up in the hierachy.
            $parent = $parent->getParent();
        }

        return null; //The inherited value is never resolved. Should be treated as false, in Voters.
    }

    /**
     * Same as inherit(), but it checks if the access token has the required role.
     * @param  User  $user  the user for which the operation should be checked
     * @param array  $roles  The roles associated with the authentication token
     * @param  string  $permission  the name of the permission for which should be checked
     * @param  string  $operation  the name of the operation for which should be checked
     *
     * @return bool|null true, if the user is allowed to do the operation (ALLOW), false if not (DISALLOW), and null,
     *                    if the value is set to inherit
     */
    public function inheritWithAPILevel(User $user, array $roles, string $permission, string $operation): ?bool
    {
        //Check that the permission/operation combination is valid
        if (! $this->isValidOperation($permission, $operation)) {
            throw new InvalidArgumentException('The permission/operation combination "'.$permission.'/'.$operation.'" is not valid!');
        }

        //Get what API level we require for the permission/operation
        $level_role = $this->permission_structure['perms'][$permission]['operations'][$operation]['apiTokenRole'];

        //When no role was set (or it is null), then the operation is blocked for API access
        if (null === $level_role) {
            return false;
        }

        //Otherwise check if the token has the required role, if not, then the operation is blocked for API access
        if (!in_array($level_role, $roles, true)) {
            return false;
        }

        //If we have the required role, then we can check the permission
        return $this->inherit($user, $permission, $operation);
    }

    /**
     * Sets the new value for the operation.
     *
     * @param HasPermissionsInterface $user       the user or group for which the value should be changed
     * @param string                  $permission the name of the permission that should be changed
     * @param string                  $operation  the name of the operation that should be changed
     * @param bool|null               $new_val    The new value for the permission. true = ALLOW, false = DISALLOW, null = INHERIT
     */
    public function setPermission(HasPermissionsInterface $user, string $permission, string $operation, ?bool $new_val): void
    {
        //Get the permissions from the user
        $perm_list = $user->getPermissions();

        //Check if the permission/operation combination is valid
        if (! $this->isValidOperation($permission, $operation)) {
            throw new InvalidArgumentException(sprintf('The permission/operation combination "%s.%s" is not valid!', $permission, $operation));
        }

        $perm_list->setPermissionValue($permission, $operation, $new_val);
    }

    /**
     * Lists the names of all operations that is supported for the given permission.
     *
     * If the Permission is not existing at all, an exception is thrown.
     *
     * This function is useful for the support() function of the voters.
     *
     * @param string $permission The permission for which the
     *
     * @return string[] A list of all operations that are supported by the given
     */
    public function listOperationsForPermission(string $permission): array
    {
        if (!$this->isValidPermission($permission)) {
            throw new InvalidArgumentException(sprintf('A permission with that name is not existing! Got %s.', $permission));
        }
        $operations = $this->permission_structure['perms'][$permission]['operations'];

        return array_keys($operations);
    }

    /**
     * Checks if the permission with the given name is existing.
     *
     * @param string $permission the name of the permission which we want to check
     *
     * @return bool True if a perm with that name is existing. False if not.
     */
    public function isValidPermission(string $permission): bool
    {
        return isset($this->permission_structure['perms'][$permission]);
    }

    /**
     * Checks if the permission operation combination with the given names is existing.
     *
     * @param string $permission the name of the permission which should be checked
     * @param string $operation  the name of the operation which should be checked
     *
     * @return bool true if the given permission operation combination is existing
     */
    public function isValidOperation(string $permission, string $operation): bool
    {
        return $this->isValidPermission($permission) &&
            isset($this->permission_structure['perms'][$permission]['operations'][$operation]);
    }

    /**
     * This functions sets all operations mentioned in the alsoSet value of a permission, so that the structure is always valid.
     * This function should be called after every setPermission() call.
     * @return bool true if values were changed/corrected, false if not
     */
    public function ensureCorrectSetOperations(HasPermissionsInterface $user): bool
    {
        //If we have changed anything on the permission structure due to the alsoSet value, this becomes true, so we
        //redo the whole process, to ensure that all alsoSet values are set recursively.

        $return_value = false;

        do {
            $anything_changed = false; //Reset the variable for the next iteration

            //Check for each permission and operation, for an alsoSet attribute
            foreach ($this->permission_structure['perms'] as $perm_key => $permission) {
                foreach ($permission['operations'] as $op_key => $op) {
                    if (!empty($op['alsoSet']) &&
                        true === $this->dontInherit($user, $perm_key, $op_key)) {
                        //Set every op listed in also Set
                        foreach ($op['alsoSet'] as $set_also) {
                            //If the alsoSet value contains a dot then we set the operation of another permission
                            [$set_perm, $set_op] = str_contains((string) $set_also, '.') ? explode('.', (string) $set_also) : [$perm_key, $set_also];

                            //Check if we change the value of the permission
                            if ($this->dontInherit($user, $set_perm, $set_op) !== true) {
                                $this->setPermission($user, $set_perm, $set_op, true);
                                //Mark the change, so we redo the whole process
                                $anything_changed = true;
                                $return_value = true;
                            }
                        }
                    }
                }
            }
        } while($anything_changed);

        return $return_value;
    }

    /**
     * Sets all possible operations of all possible permissions of the given entity to the given value.
     */
    public function setAllPermissions(HasPermissionsInterface $perm_holder, ?bool $new_value): void
    {
        foreach ($this->permission_structure['perms'] as $perm_key => $permission) {
            foreach ($permission['operations'] as $op_key => $op) {
                $this->setPermission($perm_holder, $perm_key, $op_key, $new_value);
            }
        }
    }

    /**
     * Sets all operations of the given permissions to the given value.
     * Please note that you have to call ensureCorrectSetOperations() after this function, to ensure that all alsoSet values are set.
     */
    public function setAllOperationsOfPermission(HasPermissionsInterface $perm_holder, string $permission, ?bool $new_value): void
    {
        if (!$this->isValidPermission($permission)) {
            throw new InvalidArgumentException(sprintf('A permission with that name is not existing! Got %s.', $permission));
        }

        foreach ($this->permission_structure['perms'][$permission]['operations'] as $op_key => $op) {
            $this->setPermission($perm_holder, $permission, $op_key, $new_value);
        }
    }

    /**
     * This function sets all operations of the given permission to the given value, except the ones listed in the except array.
     */
    public function setAllOperationsOfPermissionExcept(HasPermissionsInterface $perm_holder, string $permission, ?bool $new_value, array $except): void
    {
        if (!$this->isValidPermission($permission)) {
            throw new InvalidArgumentException(sprintf('A permission with that name is not existing! Got %s.', $permission));
        }

        foreach ($this->permission_structure['perms'][$permission]['operations'] as $op_key => $op) {
            if (in_array($op_key, $except, true)) {
                continue;
            }
            $this->setPermission($perm_holder, $permission, $op_key, $new_value);
        }
    }

    /**
     * This function checks if the given user has any permission set to allow, either directly or inherited.
     * @param  User  $user
     * @return bool
     */
    public function hasAnyPermissionSetToAllowInherited(User $user): bool
    {
        //Iterate over all permissions
        foreach ($this->permission_structure['perms'] as $perm_key => $permission) {
            //Iterate over all operations of the permission
            foreach ($permission['operations'] as $op_key => $op) {
                //Check if the user has the permission set to allow
                if ($this->inherit($user, $perm_key, $op_key) === true) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function generatePermissionStructure()
    {
        $cache = new ConfigCache($this->cache_file, $this->kernel_debug_enabled);

        //Check if the cache is fresh, else regenerate it.
        if (!$cache->isFresh()) {
            $permission_file = __DIR__.'/../../../config/permissions.yaml';

            //Read the permission config file...
            $config = Yaml::parse(
                file_get_contents($permission_file)
            );

            $configs = [$config];

            //... And parse it
            $processor = new Processor();
            $databaseConfiguration = new PermissionsConfiguration();
            $processedConfiguration = $processor->processConfiguration(
                $databaseConfiguration,
                $configs
            );

            //Permission file is our file resource (it is used to invalidate cache)
            $resources = [];
            $resources[] = new FileResource($permission_file);

            //Var export the structure and write it to cache file.
            $cache->write(
                sprintf('<?php return %s;', var_export($processedConfiguration, true)),
                $resources);
        }

        //In the most cases we just need to dump the cached PHP file.
        return require $this->cache_file;
    }
}
