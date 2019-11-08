<?php
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

declare(strict_types=1);

/**
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/.
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
 */

namespace App\Entity\Parts;

use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\AttachmentContainingDBElement;
use App\Entity\Base\MasterAttachmentTrait;
use App\Entity\Devices\Device;
use App\Entity\Parts\PartTraits\AdvancedPropertyTrait;
use App\Entity\Parts\PartTraits\BasicPropertyTrait;
use App\Entity\Parts\PartTraits\InstockTrait;
use App\Entity\Parts\PartTraits\ManufacturerTrait;
use App\Entity\Parts\PartTraits\OrderTrait;
use App\Security\Annotations\ColumnSecurity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Part class.
 *
 * The class properties are split over various traits in directory PartTraits.
 * Otherwise this class would be too big, to be maintained.
 *
 * @ORM\Entity(repositoryClass="App\Repository\PartRepository")
 * @ORM\Table("`parts`")
 */
class Part extends AttachmentContainingDBElement
{
    use AdvancedPropertyTrait;
    //use MasterAttachmentTrait;
    use BasicPropertyTrait;
    use InstockTrait;
    use ManufacturerTrait;
    use OrderTrait;

    //TODO
    protected $devices;

    /**
     * @ColumnSecurity(type="datetime")
     * @ORM\Column(type="datetime", name="datetime_added", options={"default"="CURRENT_TIMESTAMP"})
     */
    protected $addedDate;

    /**
     * @var \DateTime The date when this element was modified the last time.
     * @ColumnSecurity(type="datetime")
     * @ORM\Column(type="datetime", name="last_modified", options={"default"="CURRENT_TIMESTAMP"})
     */
    protected $lastModified;

    /***************************************************************
     * Overriden properties
     * (They are defined here and not in a trait, to avoid conflicts)
     ****************************************************************/

    /**
     * @var string The name of this part
     * @ORM\Column(type="string")
     * @ColumnSecurity(prefix="name")
     */
    protected $name = '';

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Attachments\PartAttachment", mappedBy="element", cascade={"persist", "remove"}, orphanRemoval=true)
     * @ColumnSecurity(type="collection", prefix="attachments")
     * @Assert\Valid()
     */
    protected $attachments;

    /**
     * @var Attachment
     * @ORM\ManyToOne(targetEntity="App\Entity\Attachments\Attachment")
     * @ORM\JoinColumn(name="id_preview_attachement", referencedColumnName="id")
     * @Assert\Expression("value == null or value.isPicture()", message="part.master_attachment.must_be_picture")
     */
    protected $master_picture_attachment;

    public function __construct()
    {
        parent::__construct();
        $this->partLots = new ArrayCollection();
        $this->orderdetails = new ArrayCollection();
    }

    /**
     * Returns the ID as an string, defined by the element class.
     * This should have a form like P000014, for a part with ID 14.
     *
     * @return string The ID as a string;
     */
    public function getIDString(): string
    {
        return 'P'.sprintf('%06d', $this->getID());
    }

    /**
     *  Get all devices which uses this part.
     *
     * @return Device[] * all devices which uses this part as a one-dimensional array of Device objects
     *                  (empty array if there are no ones)
     *                  * the array is sorted by the devices names
     */
    public function getDevices(): array
    {
        return $this->devices;
    }
}
