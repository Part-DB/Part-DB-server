<?php
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

namespace App\Entity\Attachments;

use App\Entity\Parts\Storelocation;
use Doctrine\ORM\Mapping as ORM;

/**
 * A attachment attached to a measurement unit element.
 *
 * @ORM\Entity()
 */
class StorelocationAttachment extends Attachment
{
    /**
     * @var Storelocation The element this attachment is associated with.
     * @ORM\ManyToOne(targetEntity="App\Entity\Parts\Storelocation", inversedBy="attachments")
     * @ORM\JoinColumn(name="element_id", referencedColumnName="id", nullable=false, onDelete="CASCADE").
     */
    protected $element;

    public const ALLOWED_ELEMENT_CLASS = Storelocation::class;
}
