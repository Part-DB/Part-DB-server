<?php
/**
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/.
 *
 * part-db version 0.2+
 * Copyright (C) 2009 K. Jacobs and others (see authors.php)
 * http://code.google.com/p/part-db/
 *
 * Part-DB Version 0.4+
 * Copyright (C) 2016 - 2019 Jan Böhmer
 * https://github.com/jbtronics
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

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
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
    public const TOOLS = 'tools';
    public const PARTS = 'parts';
    public const PARTS_NAME = 'parts_name';
    public const PARTS_DESCRIPTION = 'parts_description';
    public const PARTS_INSTOCK = 'parts_instock';
    public const PARTS_MININSTOCK = 'parts_mininstock';
    public const PARTS_FOOTPRINT = 'parts_footprint';
    public const PARTS_COMMENT = 'parts_comment';
    public const PARTS_STORELOCATION = 'parts_storelocation';
    public const PARTS_MANUFACTURER = 'parts_manufacturer';
    public const PARTS_ORDERDETAILS = 'parts_orderdetails';
    public const PARTS_PRICES = 'parts_prices';
    public const PARTS_ATTACHMENTS = 'parts_attachments';
    public const PARTS_ORDER = 'parts_order';
    public const GROUPS = 'groups';
    public const USERS = 'users';
    public const DATABASE = 'system_database';
    public const CONFIG = 'system_config';
    public const SYSTEM = 'system';
    public const DEVICE_PARTS = 'devices_parts';
    public const SELF = 'self';
    public const LABELS = 'labels';

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    protected $system;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    protected $groups;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    protected $users;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    protected $self;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    protected $system_config;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    protected $system_database;

    /**
     * @var int
     * @ORM\Column(type="bigint")
     */
    protected $parts;

    /**
     * @var int
     * @ORM\Column(type="smallint")
     */
    protected $parts_name;

    /**
     * @var int
     * @ORM\Column(type="smallint")
     */
    protected $parts_description;

    /**
     * @var int
     * @ORM\Column(type="smallint")
     */
    protected $parts_instock;

    /**
     * @var int
     * @ORM\Column(type="smallint")
     */
    protected $parts_mininstock;

    /**
     * @var int
     * @ORM\Column(type="smallint")
     */
    protected $parts_footprint;

    /**
     * @var int
     * @ORM\Column(type="smallint")
     */
    protected $parts_storelocation;

    /**
     * @var int
     * @ORM\Column(type="smallint")
     */
    protected $parts_manufacturer;

    /**
     * @var int
     * @ORM\Column(type="smallint")
     */
    protected $parts_comment;

    /**
     * @var int
     * @ORM\Column(type="smallint")
     */
    protected $parts_order;

    /**
     * @var int
     * @ORM\Column(type="smallint")
     */
    protected $parts_orderdetails;

    /**
     * @var int
     * @ORM\Column(type="smallint")
     */
    protected $parts_prices;

    /**
     * @var int
     * @ORM\Column(type="smallint", name="parts_attachements")
     */
    protected $parts_attachments;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    protected $devices;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    protected $devices_parts;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    protected $storelocations;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    protected $footprints;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    protected $categories;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    protected $suppliers;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    protected $manufacturers;

    /**
     * @var int
     * @ORM\Column(type="integer", name="attachement_types")
     */
    protected $attachment_types;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    protected $tools;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    protected $labels;

    /**
     * Returns the bit pair value of the given permission.
     *
     * @param string $permission_name The name of the permission, for which the bit pair should be returned.
     * @param int    $bit_n           The (lower) bit number of the bit pair, which should be read.
     *
     * @return int The value of the bit pair. Compare to the INHERIT, ALLOW, and DISALLOW consts in this class.
     */
    public function getBitValue(string $permission_name, int $bit_n): int
    {
        $perm_int = $this->$permission_name;

        return static::readBitPair($perm_int, $bit_n);
    }

    /**
     * Returns the value of the operation for the given permission.
     *
     * @param string $permission_name The name of the permission, for which the operation should be returned.
     * @param int    $bit_n           The (lower) bit number of the bit pair for the operation.
     *
     * @return bool|null The value of the operation. True, if the given operation is allowed, false if disallowed
     *                   and null if it should inherit from parent.
     */
    public function getPermissionValue(string $permission_name, int $bit_n): ?bool
    {
        $value = $this->getBitValue($permission_name, $bit_n);
        if (self::ALLOW == $value) {
            return true;
        }

        if (self::DISALLOW == $value) {
            return false;
        }

        return null;
    }

    /**
     * Reads a bit pair from $data.
     *
     * @param $data int The data from where the bits should be extracted from.
     * @param $n int The number of the lower bit (of the pair) that should be read. Starting from zero.
     *
     * @return int The value of the bit pair.
     */
    final protected static function readBitPair(int $data, int $n): int
    {
        Assert::lessThanEq($n, 31, '$n must be smaller than 32, because only a 32bit int is used! Got %s.');
        if (0 !== $n % 2) {
            throw new \InvalidArgumentException('$n must be dividable by 2, because we address bit pairs here!');
        }

        $mask = 0b11 << $n; //Create a mask for the data
        return ($data & $mask) >> $n; //Apply mask and shift back
    }

    /**
     * Writes a bit pair in the given $data and returns it.
     *
     * @param $data int The data which should be modified.
     * @param $n int The number of the lower bit of the pair which should be written.
     * @param $new int The new value of the pair.
     *
     * @return int The new data with the modified pair.
     */
    final protected static function writeBitPair(int $data, int $n, int $new): int
    {
        Assert::lessThanEq($n, 31, '$n must be smaller than 32, because only a 32bit int is used! Got %s.');
        Assert::lessThanEq($new, 3, '$new must be smaller than 3, because a bit pair is written! Got %s.');

        if (0 !== $n % 2) {
            throw new \InvalidArgumentException('$n must be dividable by 2, because we address bit pairs here!');
        }

        $mask = 0b11 << $n; //Mask all bits that should be writen
        $newval = $new << $n; //The new value.
        $data = ($data & ~$mask) | ($newval & $mask);

        return $data;
    }
}
