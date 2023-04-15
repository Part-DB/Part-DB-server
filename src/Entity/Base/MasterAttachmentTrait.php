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

namespace App\Entity\Base;

use App\Entity\Attachments\Attachment;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A entity with this class has a master attachment, which is used as a preview image for this object.
 */
trait MasterAttachmentTrait
{
    /**
     * @var Attachment|null
     * @ORM\ManyToOne(targetEntity="App\Entity\Attachments\Attachment")
     * @ORM\JoinColumn(name="id_preview_attachment", referencedColumnName="id", onDelete="SET NULL", nullable=true)
     * @Assert\Expression("value == null or value.isPicture()", message="part.master_attachment.must_be_picture")
     */
    protected ?Attachment $master_picture_attachment = null;

    /**
     * Get the master picture "Attachment"-object of this part (if there is one).
     * The master picture should be used as a visual description/representation of this part.
     *
     * @return Attachment the master picture Attachment of this part (if there is one)
     */
    public function getMasterPictureAttachment(): ?Attachment
    {
        return $this->master_picture_attachment;
    }

    /**
     * Sets the new master picture for this part.
     *
     * @return $this
     */
    public function setMasterPictureAttachment(?Attachment $new_master_attachment): self
    {
        $this->master_picture_attachment = $new_master_attachment;

        return $this;
    }
}
