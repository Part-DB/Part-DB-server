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

namespace App\EntityListeners;

use App\Entity\Attachments\Attachment;
use App\Services\Attachments\AttachmentManager;
use App\Services\Attachments\AttachmentPathResolver;
use App\Services\Attachments\AttachmentReverseSearch;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\PostRemove;
use Doctrine\ORM\Mapping\PreUpdate;
use SplFileInfo;

/**
 * This listener watches for changes on attachments and deletes the files associated with an attachment, that are not
 * used anymore. This can happen after an attachment is deleted or the path is changed.
 */
class AttachmentDeleteListener
{
    public function __construct(protected AttachmentReverseSearch $attachmentReverseSearch, protected AttachmentManager $attachmentHelper, protected AttachmentPathResolver $pathResolver)
    {
    }

    /**
     * Removes the file associated with the attachment, if the file associated with the attachment changes.
     */
    #[PreUpdate]
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
     */
    #[ORM\PreRemove]
    public function preRemoveHandler(Attachment $attachment, PreRemoveEventArgs $event): void
    {
        //Ensure that the attachment that will be deleted, is not used as preview picture anymore...
        $attachment_holder = $attachment->getElement();

        if (!$attachment_holder instanceof \App\Entity\Attachments\AttachmentContainingDBElement) {
            return;
        }

        //... Otherwise remove it as preview picture
        if ($attachment_holder->getMasterPictureAttachment() === $attachment) {
            $attachment_holder->setMasterPictureAttachment(null);

            //Recalculate the changes on the attachment holder, so the master picture change is really written to DB
            $em = $event->getObjectManager();
            if (!$em instanceof EntityManagerInterface) {
                throw new \RuntimeException('Invalid EntityManagerInterface!');
            }
            $classMetadata = $em->getClassMetadata($attachment_holder::class);
            $em->getUnitOfWork()->computeChangeSet($classMetadata, $attachment_holder);
        }
    }

    /**
     * Removes the file associated with the attachment, after the attachment was deleted.
     */
    #[PostRemove]
    public function postRemoveHandler(Attachment $attachment, PostRemoveEventArgs $event): void
    {
        //Dont delete file if the attachment uses a builtin ressource:
        if ($attachment->isBuiltIn()) {
            return;
        }

        $file = $this->attachmentHelper->attachmentToFile($attachment);
        //Only delete if the attachment has a valid file.
        if ($file instanceof \SplFileInfo) {
            /* The original file has already been removed, so we have to decrease the threshold to zero,
            as any remaining attachment depends on this attachment, and we must not delete this file! */
            $this->attachmentReverseSearch->deleteIfNotUsed($file, 0);
        }
    }
}
