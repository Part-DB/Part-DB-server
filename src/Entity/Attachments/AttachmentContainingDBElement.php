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

namespace App\Entity\Attachments;

use App\Entity\Base\MasterAttachmentTrait;
use App\Entity\Base\AbstractNamedDBElement;
use App\Entity\Contracts\HasAttachmentsInterface;
use App\Entity\Contracts\HasMasterAttachmentInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\MappedSuperclass()
 */
abstract class AttachmentContainingDBElement extends AbstractNamedDBElement implements HasMasterAttachmentInterface, HasAttachmentsInterface
{
    use MasterAttachmentTrait;

    /**
     * @var Attachment[]|Collection
     *                   //TODO
     *                   //@ORM\OneToMany(targetEntity="Attachment", mappedBy="element")
     *
     * Mapping is done in sub classes like part
     */
    protected $attachments;

    public function __construct()
    {
        $this->attachments = new ArrayCollection();
    }

    /********************************************************************************
     *
     *   Getters
     *
     *********************************************************************************/

    /**
     * Gets all attachments associated with this element.
     *
     * @return Attachment[]|Collection
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
     * @param  Attachment  $attachment
     * @return $this
     */
    public function removeAttachment(Attachment $attachment): self
    {
        $this->attachments->removeElement($attachment);

        return $this;
    }

    public function __clone()
    {
        if ($this->id) {
            $attachments = $this->attachments;
            $this->attachments = new ArrayCollection();
            //Set master attachment is needed
            foreach ($attachments as $attachment) {
                $clone = clone $attachment;
                if ($attachment === $this->master_picture_attachment) {
                    $this->setMasterPictureAttachment($clone);
                }
                $this->addAttachment($clone);
            }
        }

        //Parent has to be last call, as it resets the ID
        parent::__clone();
    }
}
