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
     * @var array This array contains the permission values for each permission
     * This array contains the permission values for each permission, in the form of:
     * permission => [
     *     operation => value,
     * ]
     * @ORM\Column(type="json", name="data", options={"default": "[]"})
     */
    protected ?array $data = [];

    /**
     * Creates a new Permission Data Instance using the given data.
     * By default, a empty array is used, meaning
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Check if a permission value is set for the given permission and operation (meaning there value is not inherit).
     * @param  string  $permission
     * @param  string  $operation
     * @return bool True if the permission value is set, false otherwise
     */
    public function isPermissionSet(string $permission, string $operation): bool
    {
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
}