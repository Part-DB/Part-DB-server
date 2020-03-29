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

namespace App\Entity\UserSystem;

use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Webmozart\Assert\Assert;

/**
 * This entity represents the permission fields a user or group can have.
 *
 * @ORM\Embeddable()
 */
class PermissionsEmbed
{
    /**
     * Permission values.
     */
    public const INHERIT = 0b00;
    public const ALLOW = 0b01;
    public const DISALLOW = 0b10;

    /**
     * Permission strings.
     */
    public const STORELOCATIONS = 'storelocations';
    public const FOOTRPINTS = 'footprints';
    public const CATEGORIES = 'categories';
    public const SUPPLIERS = 'suppliers';
    public const MANUFACTURERS = 'manufacturers';
    public const DEVICES = 'devices';
    public const ATTACHMENT_TYPES = 'attachment_types';
    public const MEASUREMENT_UNITS = 'measurement_units';
    public const CURRENCIES = 'currencies';
    public const TOOLS = 'tools';
    public const PARTS = 'parts';
    public const PARTS_NAME = 'parts_name';
    public const PARTS_DESCRIPTION = 'parts_description';
    public const PARTS_MINAMOUNT = 'parts_minamount';
    public const PARTS_FOOTPRINT = 'parts_footprint';
    public const PARTS_MPN = 'parts_mpn';
    public const PARTS_STATUS = 'parts_status';
    public const PARTS_TAGS = 'parts_tags';
    public const PARTS_UNIT = 'parts_unit';
    public const PARTS_MASS = 'parts_mass';
    public const PARTS_LOTS = 'parts_lots';
    public const PARTS_COMMENT = 'parts_comment';
    public const PARTS_MANUFACTURER = 'parts_manufacturer';
    public const PARTS_ORDERDETAILS = 'parts_orderdetails';
    public const PARTS_PRICES = 'parts_prices';
    public const PARTS_ATTACHMENTS = 'parts_attachments';
    public const PARTS_ORDER = 'parts_order';
    public const GROUPS = 'groups';
    public const USERS = 'users';
    public const DATABASE = 'database';
    public const CONFIG = 'config';
    public const SYSTEM = 'system';
    public const DEVICE_PARTS = 'devices_parts';
    public const SELF = 'self';
    public const LABELS = 'labels';

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    protected $system = 0;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    protected $groups = 0;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    protected $users = 0;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    protected $self = 0;

    /**
     * @var int
     * @ORM\Column(type="integer", name="system_config")
     */
    protected $config = 0;

    /**
     * @var int
     * @ORM\Column(type="integer", name="system_database")
     */
    protected $database = 0;

    /**
     * @var int
     * @ORM\Column(type="bigint")
     */
    protected $parts = 0;

    /**
     * @var int
     * @ORM\Column(type="smallint")
     */
    protected $parts_name = 0;

    /** @var int
     * @ORM\Column(type="smallint")
     */
    protected $parts_category = 0;

    /**
     * @var int
     * @ORM\Column(type="smallint")
     */
    protected $parts_description = 0;

    /**
     * @var int
     * @ORM\Column(type="smallint")
     */
    protected $parts_minamount = 0;

    /**
     * @var int
     * @ORM\Column(type="smallint")
     */
    protected $parts_footprint = 0;

    /**
     * @var int
     * @ORM\Column(type="smallint")
     */
    protected $parts_lots = 0;

    /**
     * @var int
     * @ORM\Column(type="smallint")
     */
    protected $parts_tags = 0;

    /** @var int
     * @ORM\Column(type="smallint")
     */
    protected $parts_unit = 0;

    /**
     * @var int
     * @ORM\Column(type="smallint")
     */
    protected $parts_mass = 0;

    /**
     * @var int
     * @ORM\Column(type="smallint")
     */
    protected $parts_manufacturer = 0;

    /**
     * @var int
     * @ORM\Column(type="smallint")
     */
    protected $parts_status = 0;

    /**
     * @var int
     * @ORM\Column(type="smallint")
     */
    protected $parts_mpn = 0;

    /**
     * @var int
     * @ORM\Column(type="smallint")
     */
    protected $parts_comment = 0;

    /**
     * @var int
     * @ORM\Column(type="smallint")
     */
    protected $parts_order = 0;

    /**
     * @var int
     * @ORM\Column(type="smallint")
     */
    protected $parts_orderdetails = 0;

    /**
     * @var int
     * @ORM\Column(type="smallint")
     */
    protected $parts_prices = 0;

    /**
     * @var int
     * @ORM\Column(type="smallint")
     */
    protected $parts_parameters = 0;

    /**
     * @var int
     * @ORM\Column(type="smallint", name="parts_attachements")
     */
    protected $parts_attachments = 0;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    protected $devices = 0;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    protected $devices_parts = 0;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    protected $storelocations = 0;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    protected $footprints = 0;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    protected $categories = 0;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    protected $suppliers = 0;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    protected $manufacturers = 0;

    /**
     * @var int
     * @ORM\Column(type="integer", name="attachement_types")
     */
    protected $attachment_types = 0;

    /** @var int
     * @ORM\Column(type="integer")
     */
    protected $currencies = 0;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    protected $measurement_units = 0;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    protected $tools = 0;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    protected $labels = 0;

    /**
     * Checks whether a permission with the given name is valid for this object.
     *
     * @param string $permission_name the name of the permission which should be checked for
     *
     * @return bool true if the permission is existing on this object
     */
    public function isValidPermissionName(string $permission_name): bool
    {
        return isset($this->{$permission_name});
    }

    /**
     * Returns the bit pair value of the given permission.
     *
     * @param string $permission_name the name of the permission, for which the bit pair should be returned
     * @param int    $bit_n           the (lower) bit number of the bit pair, which should be read
     *
     * @return int The value of the bit pair. Compare to the INHERIT, ALLOW, and DISALLOW consts in this class.
     */
    public function getBitValue(string $permission_name, int $bit_n): int
    {
        if (! $this->isValidPermissionName($permission_name)) {
            throw new InvalidArgumentException(sprintf('No permission with the name "%s" is existing!', $permission_name));
        }

        $perm_int = (int) $this->{$permission_name};

        return static::readBitPair($perm_int, $bit_n);
    }

    /**
     * Returns the value of the operation for the given permission.
     *
     * @param string $permission_name the name of the permission, for which the operation should be returned
     * @param int    $bit_n           the (lower) bit number of the bit pair for the operation
     *
     * @return bool|null The value of the operation. True, if the given operation is allowed, false if disallowed
     *                   and null if it should inherit from parent.
     */
    public function getPermissionValue(string $permission_name, int $bit_n): ?bool
    {
        $value = $this->getBitValue($permission_name, $bit_n);
        if (self::ALLOW === $value) {
            return true;
        }

        if (self::DISALLOW === $value) {
            return false;
        }

        return null;
    }

    /**
     * Sets the value of the given permission and operation.
     *
     * @param string    $permission_name the name of the permission, for which the bit pair should be written
     * @param int       $bit_n           the (lower) bit number of the bit pair, which should be written
     * @param bool|null $new_value       the new value for the operation:
     *                                   True, if the given operation is allowed, false if disallowed
     *                                   and null if it should inherit from parent
     *
     * @return PermissionsEmbed the instance itself
     */
    public function setPermissionValue(string $permission_name, int $bit_n, ?bool $new_value): self
    {
        //Determine which bit value the given value is.
        if (true === $new_value) {
            $bit_value = static::ALLOW;
        } elseif (false === $new_value) {
            $bit_value = static::DISALLOW;
        } else {
            $bit_value = static::INHERIT;
        }

        $this->setBitValue($permission_name, $bit_n, $bit_value);

        return $this;
    }

    /**
     * Sets the bit value of the given permission and operation.
     *
     * @param string $permission_name the name of the permission, for which the bit pair should be written
     * @param int    $bit_n           the (lower) bit number of the bit pair, which should be written
     * @param int    $new_value       the new (bit) value of the bit pair, which should be written
     *
     * @return PermissionsEmbed the instance itself
     */
    public function setBitValue(string $permission_name, int $bit_n, int $new_value): self
    {
        if (! $this->isValidPermissionName($permission_name)) {
            throw new InvalidArgumentException('No permission with the given name is existing!');
        }

        $this->{$permission_name} = static::writeBitPair((int) $this->{$permission_name}, $bit_n, $new_value);

        return $this;
    }

    /**
     * Returns the given permission as raw int (all bit at once).
     *
     * @param string $permission_name The name of the permission, which should be retrieved.
     *                                If this is not existing an exception is thrown.
     *
     * @return int the raw permission value
     */
    public function getRawPermissionValue(string $permission_name): int
    {
        if (! $this->isValidPermissionName($permission_name)) {
            throw new InvalidArgumentException('No permission with the given name is existing!');
        }

        return $this->{$permission_name};
    }

    /**
     * Sets the given permission to the value.
     *
     * @param string $permission_name the name of the permission to that should be set
     * @param int    $value           The new value of the permissions
     *
     * @return $this
     */
    public function setRawPermissionValue(string $permission_name, int $value): self
    {
        if (! $this->isValidPermissionName($permission_name)) {
            throw new InvalidArgumentException(sprintf('No permission with the given name %s is existing!', $permission_name));
        }

        $this->{$permission_name} = $value;

        return $this;
    }

    /**
     * Sets multiple permissions at once.
     *
     * @param array      $values  An array in the form ['perm_name' => $value], containing the new data
     * @param array|null $values2 if this array is not null, the first array will treated of list of perm names,
     *                            and this array as an array of new values
     *
     * @return $this
     */
    public function setRawPermissionValues(array $values, ?array $values2 = null): self
    {
        if (! empty($values2)) {
            $values = array_combine($values, $values2);
        }

        foreach ($values as $key => $value) {
            $this->setRawPermissionValue($key, $value);
        }

        return $this;
    }

    /**
     * Reads a bit pair from $data.
     *
     * @param int|string $data The data from where the bits should be extracted from
     * @param int        $n    The number of the lower bit (of the pair) that should be read. Starting from zero.
     *
     * @return int the value of the bit pair
     */
    final protected static function readBitPair($data, int $n): int
    {
        //Assert::lessThanEq($n, 31, '$n must be smaller than 32, because only a 32bit int is used! Got %s.');
        if (0 !== $n % 2) {
            throw new InvalidArgumentException('$n must be dividable by 2, because we address bit pairs here!');
        }

        $mask = 0b11 << $n; //Create a mask for the data
        return ($data & $mask) >> $n; //Apply mask and shift back
    }

    /**
     * Writes a bit pair in the given $data and returns it.
     *
     * @param int $data The data which should be modified
     * @param int $n    The number of the lower bit of the pair which should be written
     * @param int $new  The new value of the pair
     *
     * @return int the new data with the modified pair
     */
    final protected static function writeBitPair(int $data, int $n, int $new): int
    {
        //Assert::lessThanEq($n, 31, '$n must be smaller than 32, because only a 32bit int is used! Got %s.');
        Assert::lessThanEq($new, 3, '$new must be smaller than 3, because a bit pair is written! Got %s.');
        Assert::greaterThanEq($new, 0, '$new must not be negative, because a bit pair is written! Got %s.');

        if (0 !== $n % 2) {
            throw new InvalidArgumentException('$n must be dividable by 2, because we address bit pairs here!');
        }

        $mask = 0b11 << $n; //Mask all bits that should be written
        $newval = $new << $n; //The new value.
        $data = ($data & ~$mask) | ($newval & $mask);

        return $data;
    }
}
