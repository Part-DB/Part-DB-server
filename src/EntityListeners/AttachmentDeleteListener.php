<?php

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
            //Dont delete file if the attachment uses a builtin ressource:
            if (Attachment::checkIfBuiltin($event->getOldValue('path'))) {
                return;
            }

            $file = new SplFileInfo($this->pathResolver->placeholderToRealPath($event->getOldValue('path')));
            $this->attachmentReverseSearch->deleteIfNotUsed($file);
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
            $this->attachmentReverseSearch->deleteIfNotUsed($file);
        }
    }
}
