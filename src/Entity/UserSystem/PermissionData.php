<?php
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

namespace App\Entity\UserSystem;

use Doctrine\ORM\Mapping as ORM;

/**
 * This class is used to store the permissions of a user.
 * This has to be an embeddable or otherwise doctrine could not track the changes of the underlying data array (which is serialized to JSON in the database)
 *
 * @ORM\Embeddable()
 */
final class PermissionData implements \JsonSerializable
{
    /**
     * Permission values.
     */
    public const INHERIT = null;
    public const ALLOW = true;
    public const DISALLOW = false;

    /**
     * The current schema version of the permission data
     */
    public const CURRENT_SCHEMA_VERSION = 2;

    /**
     * @var array This array contains the permission values for each permission
     * This array contains the permission values for each permission, in the form of:
     * permission => [
     *     operation => value,
     * ]
     * @ORM\Column(type="json", name="data", options={"default": "[]"})
     */
    protected ?array $data = [
        //$ prefixed entries are used for metadata
        '$ver' => self::CURRENT_SCHEMA_VERSION, //The schema version of the permission data
    ];

    /**
     * Creates a new Permission Data Instance using the given data.
     * By default, a empty array is used, meaning
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;

        //If the passed data did not contain a schema version, we set it to the current version
        if (!isset($this->data['$ver'])) {
            $this->data['$ver'] = self::CURRENT_SCHEMA_VERSION;
        }
    }

    /**
     * Checks if any of the operations of the given permission is defined (meaning it is either ALLOW or DENY)
     * @param  string  $permission
     * @return bool
     */
    public function isAnyOperationOfPermissionSet(string $permission): bool
    {
        return !empty($this->data[$permission]);
    }

    /**
     * Returns an associative array containing all defined (non-INHERIT) operations of the given permission.
     * @param  string  $permission
     * @return array An array in the form ["operation" => value], returns an empty array if no operations are defined
     */
    public function getAllDefinedOperationsOfPermission(string $permission): array
    {
        if (empty($this->data[$permission])) {
            return [];
        }

        return $this->data[$permission];
    }

    /**
     * Sets all operations of the given permission via the given array.
     * The data is an array in the form [$operation => $value], all existing values will be overwritten/deleted.
     * @param  string  $permission
     * @param  array  $data
     * @return $this
     */
    public function setAllOperationsOfPermission(string $permission, array $data): self
    {
        $this->data[$permission] = $data;

        return $this;
    }

    /**
     * Removes a whole permission from the data including all operations (effectivly setting them to INHERIT)
     * @param  string  $permission
     * @return $this
     */
    public function removePermission(string $permission): self
    {
        unset($this->data[$permission]);

        return $this;
    }

    /**
     * Check if a permission value is set for the given permission and operation (meaning there value is not inherit).
     * @param  string  $permission
     * @param  string  $operation
     * @return bool True if the permission value is set, false otherwise
     */
    public function isPermissionSet(string $permission, string $operation): bool
    {
        //We cannot access metadata via normal permission data
        if (strpos($permission, '$') !== false) {
            return false;
        }

        return isset($this->data[$permission][$operation]);
    }

    /**
     * Returns the permission value for the given permission and operation.
     * @param  string  $permission
     * @param  string  $operation
     * @return bool|null True means allow, false means disallow, null means inherit
     */
    public function getPermissionValue(string $permission, string $operation): ?bool
    {
        if ($this->isPermissionSet($permission, $operation)) {
            return $this->data[$permission][$operation];
        }

        //If the value is not set explicitly, return null (meaning inherit)
        return null;
    }

    /**
     * Sets the permission value for the given permission and operation.
     * @param  string  $permission
     * @param  string  $operation
     * @param  bool|null  $value
     * @return $this
     */
    public function setPermissionValue(string $permission, string $operation, ?bool $value): self
    {
        if ($value === null) {
            //If the value is null, unset the permission value (meaning implicit inherit)
            unset($this->data[$permission][$operation]);
        } else {
            //Otherwise, set the pemission value
            if(!isset($this->data[$permission])) {
                $this->data[$permission] = [];
            }
            $this->data[$permission][$operation] = $value;
        }

        return $this;
    }

    /**
     * Resets the saved permissions and set all operations to inherit (which means they are not defined).
     * @return $this
     */
    public function resetPermissions(): self
    {
        $this->data = [];
        return $this;
    }

    /**
     * Creates a new Permission Data Instance using the given JSON encoded data
     * @param  string  $json
     * @return static
     * @throws \JsonException
     */
    public static function fromJSON(string $json): self
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        return new self($data);
    }

    public function __clone()
    {
        $this->data = $this->data;
    }

    /**
     * Returns an JSON encodable representation of this object.
     * @return array|mixed
     */
    public function jsonSerialize()
    {
        $ret = [];

        //Filter out all empty or null values
        foreach ($this->data as $permission => $operations) {
            $ret[$permission] = array_filter($operations, function ($value) {
                return $value !== null;
            });

            //If the permission has no operations, unset it
            if (empty($ret[$permission])) {
                unset($ret[$permission]);
            }
        }

        return $ret;
    }

    /**
     * Returns the schema version of the permission data.
     * @return int The schema version of the permission data
     */
    public function getSchemaVersion(): int
    {
        return $this->data['$ver'] ?? 0;
    }

    /**
     * Sets the schema version of this permission data
     * @param  int  $new_version
     * @return $this
     */
    public function setSchemaVersion(int $new_version): self
    {
        if ($new_version < 0) {
            throw new \InvalidArgumentException('The schema version must be a positive integer');
        }

        $this->data['$ver'] = $new_version;
        return $this;
    }

}