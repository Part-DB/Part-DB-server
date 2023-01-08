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

use App\Configuration\PermissionsConfiguration;
use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\User;
use App\Security\Interfaces\HasPermissionsInterface;
use InvalidArgumentException;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Yaml\Yaml;

/**
 * This class manages the permissions of users and groups.
 * Permissions are defined in the config/permissions.yaml file, and are parsed and resolved by this class using the
 * user and hierachical group PermissionData information.
 */
class PermissionManager
{
    protected $permission_structure;

    protected bool $is_debug;
    protected string $cache_file;

    /**
     * PermissionResolver constructor.
     */
    public function __construct(bool $kernel_debug, string $kernel_cache_dir)
    {
        $cache_dir = $kernel_cache_dir;
        //Here the cached structure will be saved.
        $this->cache_file = $cache_dir.'/permissions.php.cache';
        $this->is_debug = $kernel_debug;

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
        while (null !== $parent) { //The top group, has parent == null
            //Check if our current element gives a info about disallow/allow
            $allowed = $this->dontInherit($parent, $permission, $operation);
            if (null !== $allowed) {
                return $allowed;
            }
            //Else go up in the hierachy.
            $parent = $parent->getParent();
        }

        return null; //The inherited value is never resolved. Should be treat as false, in Voters.
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
     * If the Permission is not existing at all, a exception is thrown.
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
     * @param  HasPermissionsInterface $user
     * @return void
     */
    public function ensureCorrectSetOperations(HasPermissionsInterface $user): void
    {
        //If we have changed anything on the permission structure due to the alsoSet value, this becomes true, so we
        //redo the whole process, to ensure that all alsoSet values are set recursively.
        $anything_changed = false;

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
                            if (false !== strpos($set_also, '.')) {
                                [$set_perm, $set_op] = explode('.', $set_also);
                            } else {
                                //Else we set the operation of the same permission
                                [$set_perm, $set_op] = [$perm_key, $set_also];
                            }

                            //Check if we change the value of the permission
                            if ($this->dontInherit($user, $set_perm, $set_op) !== true) {
                                $this->setPermission($user, $set_perm, $set_op, true);
                                //Mark the change, so we redo the whole process
                                $anything_changed = true;
                            }
                        }
                    }
                }
            }
        } while($anything_changed);
    }

    /**
     * Sets all possible operations of all possible permissions of the given entity to the given value.
     * @param  HasPermissionsInterface  $perm_holder
     * @param  bool|null  $new_value
     * @return void
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
     *
     * @param  HasPermissionsInterface  $perm_holder
     * @param  string  $permission
     * @param  bool|null  $new_value
     * @return void
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

    protected function generatePermissionStructure()
    {
        $cache = new ConfigCache($this->cache_file, $this->is_debug);

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
