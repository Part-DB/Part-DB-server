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

use App\Entity\AssemblySystem\Assembly;
use App\Entity\Attachments\Attachment;

class AssemblyPreviewGenerator
{
    public function __construct(protected AttachmentManager $attachmentHelper)
    {
    }

    /**
     *  Returns a list of attachments that can be used for previewing the assembly ordered by priority.
     *
     * @param Assembly $assembly the assembly for which the attachments should be determined
     *
     * @return (Attachment|null)[]
     *
     * @psalm-return list<Attachment|null>
     */
    public function getPreviewAttachments(Assembly $assembly): array
    {
        $list = [];

        //Master attachment has top priority
        $attachment = $assembly->getMasterPictureAttachment();
        if ($this->isAttachmentValidPicture($attachment)) {
            $list[] = $attachment;
        }

        //Then comes the other images of the assembly
        foreach ($assembly->getAttachments() as $attachment) {
            //Dont show the master attachment twice
            if ($this->isAttachmentValidPicture($attachment) && $attachment !== $assembly->getMasterPictureAttachment()) {
                $list[] = $attachment;
            }
        }

        return $list;
    }

    /**
     * Determines what attachment should be used for previewing a assembly (especially in assembly table).
     * The returned attachment is guaranteed to be existing and be a picture.
     *
     * @param Assembly $assembly The assembly for which the attachment should be determined
     */
    public function getTablePreviewAttachment(Assembly $assembly): ?Attachment
    {
        $attachment = $assembly->getMasterPictureAttachment();
        if ($this->isAttachmentValidPicture($attachment)) {
            return $attachment;
        }

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
