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

namespace App\Entity\Devices;

use App\Entity\Base\AbstractDBElement;
use App\Entity\Parts\Part;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class DevicePart.
 *
 * @ORM\Table("`device_parts`")
 * @ORM\Entity()
 */
class DevicePart extends AbstractDBElement
{
    /**
     * @var int
     * @ORM\Column(type="integer", name="quantity")
     */
    protected int $quantity;

    /**
     * @var string
     * @ORM\Column(type="text", name="mountnames")
     */
    protected string $mountnames;
    /**
     * @var Device
     * @ORM\ManyToOne(targetEntity="Device", inversedBy="parts")
     * @ORM\JoinColumn(name="id_device", referencedColumnName="id")
     */
    protected Device $device;

    /**
     * @var Part
     * @ORM\ManyToOne(targetEntity="App\Entity\Parts\Part")
     * @ORM\JoinColumn(name="id_part", referencedColumnName="id")
     */
    protected Part $part;
}
