<?php

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

namespace App\Services;

use App\Configuration\PermissionsConfiguration;
use App\Entity\UserSystem\User;
use App\Security\Interfaces\HasPermissionsInterface;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Yaml\Yaml;

class PermissionResolver
{
    protected $permission_structure;

    protected $is_debug;
    protected $cache_file;

    /**
     * PermissionResolver constructor.
     */
    public function __construct(ParameterBagInterface $params, ContainerInterface $container)
    {
        $cache_dir = $container->getParameter('kernel.cache_dir');
        //Here the cached structure will be saved.
        $this->cache_file = $cache_dir.'/permissions.php.cache';
        $this->is_debug = $container->getParameter('kernel.debug');

        $this->permission_structure = $this->generatePermissionStructure();

        //dump($this->permission_structure);
    }

    public function getPermissionStructure(): array
    {
        return $this->permission_structure;
    }

    /**
     * Check if a user/group is allowed to do the specified operation for the permission.
     *
     * See permissions.yaml for valid permission operation combinations.
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
        //Get the permissions from the user
        $perm_list = $user->getPermissions();

        //Determine bit number using our configuration
        $bit = $this->permission_structure['perms'][$permission]['operations'][$operation]['bit'];

        return $perm_list->getPermissionValue($permission, $bit);
    }

    /**
     * Checks if a user is allowed to do the specified operation for the permission.
     * In contrast to dontInherit() it tries to resolve the inherit values, of the user, by going upwards in the
     * hierachy (user -> group -> parent group -> so on). But even in this case it is possible, that the inherit value
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

        //Determine bit number using our configuration
        $bit = $this->permission_structure['perms'][$permission]['operations'][$operation]['bit'];

        $perm_list->setPermissionValue($permission, $bit, $new_val);
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
        if (! $this->isValidPermission($permission)) {
            throw new \InvalidArgumentException(sprintf('A permission with that name is not existing! Got %s.', $permission));
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

    protected function generatePermissionStructure()
    {
        $cache = new ConfigCache($this->cache_file, $this->is_debug);

        //Check if the cache is fresh, else regenerate it.
        if (! $cache->isFresh()) {
            $permission_file = __DIR__.'/../../config/permissions.yaml';

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
