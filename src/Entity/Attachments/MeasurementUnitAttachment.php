<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Entity\Attachments;

use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * An attachment attached to a measurement unit element.
 *
 * @ORM\Entity()
 * @UniqueEntity({"name", "attachment_type", "element"})
 */
class MeasurementUnitAttachment extends Attachment
{
    public const ALLOWED_ELEMENT_CLASS = MeasurementUnit::class;
    /**
     * @var Manufacturer|null the element this attachment is associated with
     * @ORM\ManyToOne(targetEntity="App\Entity\Parts\MeasurementUnit", inversedBy="attachments")
     * @ORM\JoinColumn(name="element_id", referencedColumnName="id", nullable=false, onDelete="CASCADE").
     */
    protected ?AttachmentContainingDBElement $element = null;
}
