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

namespace App\EntityListeners;

use App\Entity\Attachments\Attachment;
use App\Services\Attachments\AttachmentManager;
use App\Services\Attachments\AttachmentPathResolver;
use App\Services\Attachments\AttachmentReverseSearch;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\PostRemove;
use Doctrine\ORM\Mapping\PreUpdate;
use SplFileInfo;

/**
 * This listener watches for changes on attachments and deletes the files associated with an attachment, that are not
 * used any more. This can happens after an attachment is delteted or the path is changed.
 */
class AttachmentDeleteListener
{
    protected $attachmentReverseSearch;
    protected $attachmentHelper;
    protected $pathResolver;

    public function __construct(AttachmentReverseSearch $attachmentReverseSearch, AttachmentManager $attachmentHelper, AttachmentPathResolver $pathResolver)
    {
        $this->attachmentReverseSearch = $attachmentReverseSearch;
        $this->attachmentHelper = $attachmentHelper;
        $this->pathResolver = $pathResolver;
    }

    /**
     * Removes the file associated with the attachment, if the file associated with the attachment changes.
     *
     * @PreUpdate
     */
    public function preUpdateHandler(Attachment $attachment, PreUpdateEventArgs $event): void
    {
        if ($event->hasChangedField('path')) {
            $old_path = $event->getOldValue('path');

            //Dont delete file if the attachment uses a builtin ressource:
            if (Attachment::checkIfBuiltin($old_path)) {
                return;
            }

            $real_path = $this->pathResolver->placeholderToRealPath($old_path);

            //If the attachment does not point to a valid file, ignore it!
            if (null === $real_path) {
                return;
            }

            $file = new SplFileInfo($real_path);
            $this->attachmentReverseSearch->deleteIfNotUsed($file);
        }
    }

    /**
     * Ensure that attachments are not used in preview, so that they can be deleted (without integrity violation).
     * @ORM\PreRemove()
     */
    public function preRemoveHandler(Attachment $attachment, LifecycleEventArgs $event): void
    {
        //Ensure that the attachment that will be deleted, is not used as preview picture anymore...
        $attachment_holder = $attachment->getElement();

        if ($attachment_holder === null) {
            return;
        }

        //... Otherwise remove it as preview picture
        if ($attachment_holder->getMasterPictureAttachment() === $attachment) {
            $attachment_holder->setMasterPictureAttachment(null);
        }
    }

    /**
     * Removes the file associated with the attachment, after the attachment was deleted.
     *
     * @PostRemove
     */
    public function postRemoveHandler(Attachment $attachment, LifecycleEventArgs $event): void
    {
        //Dont delete file if the attachment uses a builtin ressource:
        if ($attachment->isBuiltIn()) {
            return;
        }

        $file = $this->attachmentHelper->attachmentToFile($attachment);
        //Only delete if the attachment has a valid file.
        if (null !== $file) {
            /* The original file has already been removed, so we have to decrease the threshold to zero,
            as any remaining attachment depends on this attachment, and we must not delete this file! */
            $this->attachmentReverseSearch->deleteIfNotUsed($file, 0);
        }
    }
}
