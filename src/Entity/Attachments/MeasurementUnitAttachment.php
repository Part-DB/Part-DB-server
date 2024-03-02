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

use App\Entity\Parts\MeasurementUnit;
use App\Serializer\APIPlatform\OverrideClassDenormalizer;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Context;

/**
 * An attachment attached to a measurement unit element.
 * @extends Attachment<MeasurementUnit>
 */
#[UniqueEntity(['name', 'attachment_type', 'element'])]
#[ORM\Entity]
class MeasurementUnitAttachment extends Attachment
{
    final public const ALLOWED_ELEMENT_CLASS = MeasurementUnit::class;


    #[ORM\ManyToOne(targetEntity: MeasurementUnit::class, inversedBy: 'attachments')]
    #[ORM\JoinColumn(name: 'element_id', nullable: false, onDelete: 'CASCADE')]
    #[Context(denormalizationContext: [OverrideClassDenormalizer::CONTEXT_KEY => self::ALLOWED_ELEMENT_CLASS])]
    protected ?AttachmentContainingDBElement $element = null;
}
