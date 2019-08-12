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

use App\Entity\Base\NamedDBElement;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Exception;

/**
 * @ORM\MappedSuperclass()
 */
abstract class AttachmentContainingDBElement extends NamedDBElement
{
    /**
     * @var Attachment[]
     * //TODO
     * //@ORM\OneToMany(targetEntity="Attachment", mappedBy="element")
     *
     * Mapping is done in sub classes like part
     */
    protected $attachments;

    //TODO
    protected $attachmentTypes;

    /********************************************************************************
     *
     *   Getters
     *
     *********************************************************************************/

    /**
     * Get all different attachement types of the attachements of this element.
     *
     * @return AttachmentType[] the attachement types as a one-dimensional array of AttachementType objects,
     *                          sorted by their names
     *
     * @throws Exception if there was an error
     */
    public function getAttachmentTypes(): ?array
    {
        return $this->attachmentTypes;
    }

    /**
     * Get all attachements of this element / Get the element's attachements with a specific type.
     *
     * @param int  $type_id                 * if NULL, all attachements of this element will be returned
     *                                      * if this is a number > 0, only attachements with this type ID will be returned
     * @param bool $only_table_attachements if true, only attachements with "show_in_table == true"
     *
     * @return Attachment[] the attachements as a one-dimensional array of Attachement objects
     *
     * @throws Exception if there was an error
     */
    public function getAttachments($type_id = null, bool $only_table_attachements = false) : Collection
    {
        if ($only_table_attachements || $type_id) {
            $attachements = $this->attachments;

            foreach ($attachements as $key => $attachement) {
                if (($only_table_attachements && (!$attachement->getShowInTable()))
                    || ($type_id && ($attachement->getType()->getID() !== $type_id))) {
                    unset($attachements[$key]);
                }
            }

            return $attachements;
        }

        return $this->attachments;
    }
}
