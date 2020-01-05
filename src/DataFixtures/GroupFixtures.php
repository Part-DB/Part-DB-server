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

namespace App\DataFixtures;

use App\Entity\UserSystem\Group;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

class GroupFixtures extends Fixture
{
    public const ADMINS = 'group-admin';
    public const USERS = 'group-users';
    public const READONLY = 'group-readonly';

    public function load(ObjectManager $manager): void
    {
        $admins = new Group();
        $admins->setName('admins');
        //Perm values taken from Version 1
        $admins->getPermissions()->setRawPermissionValues([
            'system' => 21,
            'groups' => 1365,
            'users' => 87381,
            'self' => 85,
            'config' => 85,
            'database' => 21,
            'parts' => 1431655765,
            'parts_name' => 5,
            'parts_description' => 5,
            'parts_footprint' => 5,
            'parts_manufacturer' => 5,
            'parts_comment' => 5,
            'parts_order' => 5,
            'parts_orderdetails' => 341,
            'parts_prices' => 341,
            'parts_attachments' => 341,
            'devices' => 5461,
            'devices_parts' => 325,
            'storelocations' => 5461,
            'footprints' => 5461,
            'categories' => 5461,
            'suppliers' => 5461,
            'manufacturers' => 5461,
            'attachment_types' => 1365,
            'tools' => 1365,
            'labels' => 21,
            'parts_category' => 5,
            'parts_minamount' => 5,
            'parts_lots' => 85,
            'parts_tags' => 5,
            'parts_unit' => 5,
            'parts_mass' => 5,
            'parts_status' => 5,
            'parts_mpn' => 5,
            'currencies' => 5461,
            'measurement_units' => 5461,
        ]);
        $this->setReference(self::ADMINS, $admins);
        $manager->persist($admins);

        $readonly = new Group();
        $readonly->setName('readonly');
        $readonly->getPermissions()->setRawPermissionValues([
            'system' => 2,
            'groups' => 2730,
            'users' => 43690,
            'self' => 25,
            'config' => 170,
            'database' => 42,
            'parts' => 2778027689,
            'parts_name' => 9,
            'parts_description' => 9,
            'parts_footprint' => 9,
            'parts_manufacturer' => 9,
            'parts_comment' => 9,
            'parts_order' => 9,
            'parts_orderdetails' => 681,
            'parts_prices' => 681,
            'parts_attachments' => 681,
            'devices' => 1705,
            'devices_parts' => 649,
            'storelocations' => 1705,
            'footprints' => 1705,
            'categories' => 1705,
            'suppliers' => 1705,
            'manufacturers' => 1705,
            'attachment_types' => 681,
            'tools' => 1366,
            'labels' => 165,
            'parts_category' => 9,
            'parts_minamount' => 9,
            'parts_lots' => 169,
            'parts_tags' => 9,
            'parts_unit' => 9,
            'parts_mass' => 9,
            'parts_status' => 9,
            'parts_mpn' => 9,
            'currencies' => 9897,
            'measurement_units' => 9897,
        ]);
        $this->setReference(self::READONLY, $readonly);
        $manager->persist($readonly);

        $users = new Group();
        $users->setName('users');
        $users->getPermissions()->setRawPermissionValues([
            'system' => 42,
            'groups' => 2730,
            'users' => 43690,
            'self' => 89,
            'config' => 105,
            'database' => 41,
            'parts' => 1431655765,
            'parts_name' => 5,
            'parts_description' => 5,
            'parts_footprint' => 5,
            'parts_manufacturer' => 5,
            'parts_comment' => 5,
            'parts_order' => 5,
            'parts_orderdetails' => 341,
            'parts_prices' => 341,
            'parts_attachments' => 341,
            'devices' => 5461,
            'devices_parts' => 325,
            'storelocations' => 5461,
            'footprints' => 5461,
            'categories' => 5461,
            'suppliers' => 5461,
            'manufacturers' => 5461,
            'attachment_types' => 1365,
            'tools' => 1365,
            'labels' => 85,
            'parts_category' => 5,
            'parts_minamount' => 5,
            'parts_lots' => 85,
            'parts_tags' => 5,
            'parts_unit' => 5,
            'parts_mass' => 5,
            'parts_status' => 5,
            'parts_mpn' => 5,
            'currencies' => 5461,
            'measurement_units' => 5461,
        ]);
        $this->setReference(self::USERS, $users);
        $manager->persist($users);

        $manager->flush();
    }
}
