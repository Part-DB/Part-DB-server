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

declare(strict_types=1);
/**
 * Part-DB Version 0.4+ "nextgen"
 * Copyright (C) 2016 - 2019 Jan Böhmer
 * https://github.com/jbtronics.
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

namespace App\Entity\Attachments;

use App\Entity\Base\MasterAttachmentTrait;
use App\Entity\Base\NamedDBElement;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\MappedSuperclass()
 */
abstract class AttachmentContainingDBElement extends NamedDBElement
{
    use MasterAttachmentTrait;

    /**
     * @var Attachment[]
     * //TODO
     * //@ORM\OneToMany(targetEntity="Attachment", mappedBy="element")
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
     * @return Attachment[]|Collection
     */
    public function getAttachments() : Collection
    {
        return $this->attachments;
    }

    /**
     * Adds an attachment to this element
     * @param Attachment $attachment Attachment
     * @return $this
     */
    public function addAttachment(Attachment $attachment) : self
    {
        //Attachment must be associated with this element
        $attachment->setElement($this);
        $this->attachments->add($attachment);
        return $this;
    }

    /**
     * Removes the given attachment from this element
     * @param Attachment $attachment
     * @return $this
     */
    public function removeAttachment(Attachment $attachment) : self
    {
        $this->attachments->removeElement($attachment);
        return $this;
    }
}
