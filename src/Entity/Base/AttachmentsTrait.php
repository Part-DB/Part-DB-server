<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Entity\Base;

use App\Entity\Attachments\Attachment;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Trait providing attachments functionality.
 */
trait AttachmentsTrait
{
    /**
     * @var Collection<int, Attachment>
     * ORM Mapping is done in subclasses (e.g. Part)
     */
    #[Groups(['full', 'import'])]
    protected Collection $attachments;

    /**
     * Initialize the attachments collection.
     */
    protected function initializeAttachments(): void
    {
        $this->attachments = new ArrayCollection();
    }

    /**
     *  Gets all attachments associated with this element.
     */
    public function getAttachments(): Collection
    {
        return $this->attachments;
    }

    /**
     * Adds an attachment to this element.
     *
     * @param Attachment $attachment Attachment
     *
     * @return $this
     */
    public function addAttachment(Attachment $attachment): self
    {
        //Attachment must be associated with this element
        $attachment->setElement($this);
        $this->attachments->add($attachment);

        return $this;
    }

    /**
     * Removes the given attachment from this element.
     *
     * @return $this
     */
    public function removeAttachment(Attachment $attachment): self
    {
        $this->attachments->removeElement($attachment);

        //Check if this is the master attachment -> remove it from master attachment too, or it can not be deleted from DB...
        if (isset($this->master_picture_attachment) && $attachment === $this->master_picture_attachment) {
            $this->setMasterPictureAttachment(null);
        }

        return $this;
    }

    /**
     * Clone helper for attachments - deep clones all attachments.
     */
    protected function cloneAttachments(): void
    {
        if (isset($this->id) && $this->id) {
            $attachments = $this->attachments;
            $this->attachments = new ArrayCollection();
            //Set master attachment is needed
            foreach ($attachments as $attachment) {
                $clone = clone $attachment;
                if (isset($this->master_picture_attachment) && $attachment === $this->master_picture_attachment) {
                    $this->setMasterPictureAttachment($clone);
                }
                $this->addAttachment($clone);
            }
        }
    }
}
