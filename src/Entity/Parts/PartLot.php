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

namespace App\Entity\Parts;


use App\Entity\Base\DBElement;
use App\Entity\Base\NamedDBElement;
use App\Entity\Base\TimestampTrait;
use App\Validator\Constraints\Selectable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity describes a lot where parts can be stored.
 * It is the connection between a part and its store locations.
 * @package App\Entity\Parts
 * @ORM\Entity()
 * @ORM\Table(name="part_lots")
 * @ORM\HasLifecycleCallbacks()
 */
class PartLot extends DBElement
{

    use TimestampTrait;

    /**
     * @var string A short description about this lot, shown in table
     * @ORM\Column(type="text")
     */
    protected $description;

    /**
     * @var string A comment stored with this lot.
     * @ORM\Column(type="text")
     */
    protected $comment;

    /**
     * @var \DateTime Set a time until when the lot must be used.
     * Set to null, if the lot can be used indefinitley.
     * @ORM\Column(type="datetimetz", name="expiration_date", nullable=true)
     */
    protected $expiration_date;

    /**
     * @var Storelocation The storelocation of this lot
     * @ORM\ManyToOne(targetEntity="Storelocation")
     * @ORM\JoinColumn(name="id_store_location", referencedColumnName="id")
     * @Selectable()
     */
    protected $storage_location;

    /**
     * @var Part The part that is stored in this lot
     * @ORM\ManyToOne(targetEntity="Part", inversedBy="partLots")
     * @ORM\JoinColumn(name="id_part", referencedColumnName="id")
     */
    protected $part;

    /**
     * @var bool If this is set to true, the instock amount is marked as not known
     * @ORM\Column(type="boolean")
     */
    protected $instock_unknown;

    /**
     * @var int For integer sizes the instock is saved here.
     * @ORM\Column(type="integer", nullable=true)
     * @Assert\Positive()
     */
    protected $instock;

    /**
     * @var float For continuos sizes (length, volume, etc.) the instock is saved here.
     * @ORM\Column(type="float", nullable=true)
     */
    protected $amount;

    /**
     * @var boolean Determines if this lot was manually marked for refilling.
     * @ORM\Column(type="boolean")
     */
    protected $needs_refill;

    /**
     * Returns the ID as an string, defined by the element class.
     * This should have a form like P000014, for a part with ID 14.
     *
     * @return string The ID as a string;
     *
     */
    public function getIDString(): string
    {
        return 'PL' . $this->getID();
    }
}