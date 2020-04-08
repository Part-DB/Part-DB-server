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

namespace App\Security\Voter;

use App\Entity\Attachments\AttachmentType;
use App\Entity\Devices\Device;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Storelocation;
use App\Entity\Parts\Supplier;
use App\Entity\PriceInformations\Currency;
use App\Entity\UserSystem\User;

use function get_class;
use function is_object;

class StructureVoter extends ExtendedVoter
{
    protected const OBJ_PERM_MAP = [
        AttachmentType::class => 'attachment_type',
        Category::class => 'categories',
        Device::class => 'devices',
        Footprint::class => 'footprints',
        Manufacturer::class => 'manufacturers',
        Storelocation::class => 'storelocations',
        Supplier::class => 'suppliers',
        Currency::class => 'currencies',
        MeasurementUnit::class => 'measurement_units',
    ];

    /**
     * Determines if the attribute and subject are supported by this voter.
     *
     * @param string $attribute An attribute
     * @param mixed  $subject   The subject to secure, e.g. an object the user wants to access or any other PHP type
     *
     * @return bool True if the attribute and subject are supported, false otherwise
     */
    protected function supports($attribute, $subject)
    {
        if (is_object($subject) || is_string($subject)) {
            $permission_name = $this->instanceToPermissionName($subject);
            //If permission name is null, then the subject is not supported
            return (null !== $permission_name) && $this->resolver->isValidOperation($permission_name, $attribute);
        }

        return false;
    }

    /**
     * Maps a instance type to the permission name.
     *
     * @param object|string $subject The subject for which the permission name should be generated
     *
     * @return string|null the name of the permission for the subject's type or null, if the subject is not supported
     */
    protected function instanceToPermissionName($subject): ?string
    {
        if (! is_string($subject)) {
            $class_name = get_class($subject);
        } else {
            $class_name = $subject;
        }

        //If it is existing in index, we can skip the loop
        if (isset(static::OBJ_PERM_MAP[$class_name])) {
            return static::OBJ_PERM_MAP[$class_name];
        }

        foreach (static::OBJ_PERM_MAP as $class => $ret) {
            if (is_a($class_name, $class, true)) {
                return $ret;
            }
        }

        return null;
    }

    /**
     * Similar to voteOnAttribute, but checking for the anonymous user is already done.
     * The current user (or the anonymous user) is passed by $user.
     *
     * @param string $attribute
     *
     * @return bool
     */
    protected function voteOnUser($attribute, $subject, User $user): bool
    {
        $permission_name = $this->instanceToPermissionName($subject);
        //Just resolve the permission
        return $this->resolver->inherit($user, $permission_name, $attribute) ?? false;
    }
}
