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

namespace App\Repository\Parts;


use App\Entity\Base\AbstractPartsContainingDBElement;
use App\Entity\Devices\Device;
use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use App\Repository\AbstractPartsContainingRepository;
use InvalidArgumentException;

class DeviceRepository extends AbstractPartsContainingRepository
{

    public function getParts(object $element, array $order_by = ['name' => 'ASC']): array
    {
        if (!$element instanceof Device) {
            throw new InvalidArgumentException('$element must be an Device!');
        }


        //TODO: Change this later, when properly implemented devices
        return [];
    }

    public function getPartsCount(object $element): int
    {
        if (!$element instanceof Device) {
            throw new InvalidArgumentException('$element must be an Device!');
        }

        //TODO: Change this later, when properly implemented devices
        //Prevent user from deleting devices, to not accidentally remove filled devices from old versions
        return 1;
    }
}