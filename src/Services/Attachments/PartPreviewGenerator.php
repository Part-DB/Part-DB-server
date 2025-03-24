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

namespace App\Services\Attachments;

use App\Entity\Parts\Footprint;
use App\Entity\Parts\PartCustomState;
use App\Entity\ProjectSystem\Project;
use App\Entity\Parts\Category;
use App\Entity\Parts\StorageLocation;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Manufacturer;
use App\Entity\Attachments\Attachment;
use App\Entity\Parts\Part;

class PartPreviewGenerator
{
    public function __construct(protected AttachmentManager $attachmentHelper)
    {
    }

    /**
     *  Returns a list of attachments that can be used for previewing the part ordered by priority.
     *  The priority is: Part MasterAttachment -> Footprint MasterAttachment -> Category MasterAttachment
     *  -> Storelocation Attachment -> MeasurementUnit Attachment -> ManufacturerAttachment.
     *
     * @param Part $part the part for which the attachments should be determined
     *
     * @return (Attachment|null)[]
     *
     * @psalm-return list<Attachment|null>
     */
    public function getPreviewAttachments(Part $part): array
    {
        $list = [];

        //Master attachment has top priority
        $attachment = $part->getMasterPictureAttachment();
        if ($this->isAttachmentValidPicture($attachment)) {
            $list[] = $attachment;
        }

        //Then comes the other images of the part
        foreach ($part->getAttachments() as $attachment) {
            //Dont show the master attachment twice
            if ($this->isAttachmentValidPicture($attachment) && $attachment !== $part->getMasterPictureAttachment()) {
                $list[] = $attachment;
            }
        }

        if ($part->getFootprint() instanceof Footprint) {
            $attachment = $part->getFootprint()->getMasterPictureAttachment();
            if ($this->isAttachmentValidPicture($attachment)) {
                $list[] = $attachment;
            }
        }

        if ($part->getBuiltProject() instanceof Project) {
            $attachment = $part->getBuiltProject()->getMasterPictureAttachment();
            if ($this->isAttachmentValidPicture($attachment)) {
                $list[] = $attachment;
            }
        }

        if ($part->getCategory() instanceof Category) {
            $attachment = $part->getCategory()->getMasterPictureAttachment();
            if ($this->isAttachmentValidPicture($attachment)) {
                $list[] = $attachment;
            }
        }

        foreach ($part->getPartLots() as $lot) {
            if ($lot->getStorageLocation() instanceof StorageLocation) {
                $attachment = $lot->getStorageLocation()->getMasterPictureAttachment();
                if ($this->isAttachmentValidPicture($attachment)) {
                    $list[] = $attachment;
                }
            }
        }

        if ($part->getPartUnit() instanceof MeasurementUnit) {
            $attachment = $part->getPartUnit()->getMasterPictureAttachment();
            if ($this->isAttachmentValidPicture($attachment)) {
                $list[] = $attachment;
            }
        }

        if ($part->getPartCustomState() instanceof PartCustomState) {
            $attachment = $part->getPartCustomState()->getMasterPictureAttachment();
            if ($this->isAttachmentValidPicture($attachment)) {
                $list[] = $attachment;
            }
        }

        if ($part->getManufacturer() instanceof Manufacturer) {
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

        //Otherwise check if the part has a footprint with a valid master attachment
        if ($part->getFootprint() instanceof Footprint) {
            $attachment = $part->getFootprint()->getMasterPictureAttachment();
            if ($this->isAttachmentValidPicture($attachment)) {
                return $attachment;
            }
        }

        //With lowest priority use the master attachment of the project this part represents (when existing)
        if ($part->getBuiltProject() instanceof Project) {
            $attachment = $part->getBuiltProject()->getMasterPictureAttachment();
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
        return $attachment instanceof Attachment
            && $attachment->isPicture()
            && $this->attachmentHelper->isFileExisting($attachment);
    }
}
