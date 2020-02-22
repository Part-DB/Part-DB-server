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

namespace App\Services\Attachments;

use App\Entity\Attachments\Attachment;
use App\Entity\Parts\Part;

class PartPreviewGenerator
{
    protected $attachmentHelper;

    public function __construct(AttachmentManager $attachmentHelper)
    {
        $this->attachmentHelper = $attachmentHelper;
    }

    /**
     * Returns a list of attachments that can be used for previewing the part ordered by priority.
     * The priority is: Part MasterAttachment -> Footprint MasterAttachment -> Category MasterAttachment
     * -> Storelocation Attachment -> MeasurementUnit Attachment -> ManufacturerAttachment.
     *
     * @param Part $part the part for which the attachments should be determined
     *
     * @return Attachment[]
     */
    public function getPreviewAttachments(Part $part): array
    {
        $list = [];

        //Master attachment has top priority
        $attachment = $part->getMasterPictureAttachment();
        if ($this->isAttachmentValidPicture($attachment)) {
            $list[] = $attachment;
        }

        if (null !== $part->getFootprint()) {
            $attachment = $part->getFootprint()->getMasterPictureAttachment();
            if ($this->isAttachmentValidPicture($attachment)) {
                $list[] = $attachment;
            }
        }

        if (null !== $part->getCategory()) {
            $attachment = $part->getCategory()->getMasterPictureAttachment();
            if ($this->isAttachmentValidPicture($attachment)) {
                $list[] = $attachment;
            }
        }

        foreach ($part->getPartLots() as $lot) {
            if (null !== $lot->getStorageLocation()) {
                $attachment = $lot->getStorageLocation()->getMasterPictureAttachment();
                if ($this->isAttachmentValidPicture($attachment)) {
                    $list[] = $attachment;
                }
            }
        }

        if (null !== $part->getPartUnit()) {
            $attachment = $part->getPartUnit()->getMasterPictureAttachment();
            if ($this->isAttachmentValidPicture($attachment)) {
                $list[] = $attachment;
            }
        }

        if (null !== $part->getManufacturer()) {
            $attachment = $part->getManufacturer()->getMasterPictureAttachment();
            if ($this->isAttachmentValidPicture($attachment)) {
                $list[] = $attachment;
            }
        }

        return $list;
    }

    /**
     * Determines what attachment should be used for previewing a part (especially in part table).
     * The returned attachment is guaranteed to be existing and be a picture.
     *
     * @param Part $part The part for which the attachment should be determined
     */
    public function getTablePreviewAttachment(Part $part): ?Attachment
    {
        //First of all we check if the master attachment of the part is set (and a picture)
        $attachment = $part->getMasterPictureAttachment();
        if ($this->isAttachmentValidPicture($attachment)) {
            return $attachment;
        }

        //Otherwise check if the part has a footprint with a valid masterattachment
        if (null !== $part->getFootprint()) {
            $attachment = $part->getFootprint()->getMasterPictureAttachment();
            if ($this->isAttachmentValidPicture($attachment)) {
                return $attachment;
            }
        }

        //If nothing is available return null
        return null;
    }

    /**
     * Checks if a attachment is exising and a valid picture.
     *
     * @param Attachment|null $attachment the attachment that should be checked
     *
     * @return bool true if the attachment is valid
     */
    protected function isAttachmentValidPicture(?Attachment $attachment): bool
    {
        return null !== $attachment
            && $attachment->isPicture()
            && $this->attachmentHelper->isFileExisting($attachment);
    }
}
