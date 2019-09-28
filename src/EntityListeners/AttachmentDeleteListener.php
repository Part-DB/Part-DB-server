<?php
/**
 *
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/
 *
 * part-db version 0.2+
 * Copyright (C) 2009 K. Jacobs and others (see authors.php)
 * http://code.google.com/p/part-db/
 *
 * Part-DB Version 0.4+
 * Copyright (C) 2016 - 2019 Jan Böhmer
 * https://github.com/jbtronics
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
 *
 */

namespace App\EntityListeners;


use App\Entity\Attachments\Attachment;
use App\Services\AttachmentHelper;
use App\Services\AttachmentReverseSearch;
use App\Services\Attachments\AttachmentPathResolver;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Mapping\PostRemove;
use Doctrine\ORM\Mapping\PreUpdate;

/**
 * This listener watches for changes on attachments and deletes the files associated with an attachment, that are not
 * used any more. This can happens after an attachment is delteted or the path is changed.
 * @package App\EntityListeners
 */
class AttachmentDeleteListener
{
    protected $attachmentReverseSearch;
    protected $attachmentHelper;
    protected $pathResolver;

    public function __construct(AttachmentReverseSearch $attachmentReverseSearch, AttachmentHelper $attachmentHelper, AttachmentPathResolver $pathResolver)
    {
        $this->attachmentReverseSearch = $attachmentReverseSearch;
        $this->attachmentHelper = $attachmentHelper;
        $this->pathResolver = $pathResolver;
    }

    /**
     * Removes the file associated with the attachment, if the file associated with the attachment changes.
     * @param Attachment $attachment
     * @param PreUpdateEventArgs $event
     *
     * @PreUpdate
     */
    public function preUpdateHandler(Attachment $attachment, PreUpdateEventArgs $event)
    {
        if ($event->hasChangedField('path')) {
            //Dont delete file if the attachment uses a builtin ressource:
            if (Attachment::checkIfBuiltin($event->getOldValue('path'))) {
                return;
            }

            $file = new \SplFileInfo($this->pathResolver->placeholderToRealPath($event->getOldValue('path')));
            $this->attachmentReverseSearch->deleteIfNotUsed($file);
        }
    }

    /**
     * Removes the file associated with the attachment, after the attachment was deleted.
     *
     * @param Attachment $attachment
     * @param LifecycleEventArgs $event
     *
     * @PostRemove
     */
    public function postRemoveHandler(Attachment $attachment, LifecycleEventArgs $event)
    {
        //Dont delete file if the attachment uses a builtin ressource:
        if (Attachment::checkIfBuiltin($event->getOldValue('path'))) {
            return;
        }

        $file = $this->attachmentHelper->attachmentToFile($attachment);
        //Only delete if the attachment has a valid file.
        if ($file !== null) {
            $this->attachmentReverseSearch->deleteIfNotUsed($file);
        }
    }

}