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
    protected $description = '';

    /**
     * @var string A comment stored with this lot.
     * @ORM\Column(type="text")
     */
    protected $comment = '';

    /**
     * @var ?\DateTime Set a time until when the lot must be used.
     * Set to null, if the lot can be used indefinitley.
     * @ORM\Column(type="datetimetz", name="expiration_date", nullable=true)
     */
    protected $expiration_date;

    /**
     * @var Storelocation|null The storelocation of this lot
     * @ORM\ManyToOne(targetEntity="Storelocation")
     * @ORM\JoinColumn(name="id_store_location", referencedColumnName="id", nullable=true)
     * @Selectable()
     */
    protected $storage_location;

    /**
     * @var Part The part that is stored in this lot
     * @ORM\ManyToOne(targetEntity="Part", inversedBy="partLots")
     * @ORM\JoinColumn(name="id_part", referencedColumnName="id")
     * @Assert\NotNull()
     */
    protected $part;

    /**
     * @var bool If this is set to true, the instock amount is marked as not known
     * @ORM\Column(type="boolean")
     */
    protected $instock_unknown = false;


    /**
     * @var float For continuos sizes (length, volume, etc.) the instock is saved here.
     * @ORM\Column(type="float")
     * @Assert\PositiveOrZero()
     */
    protected $amount = 0;

    /**
     * @var boolean Determines if this lot was manually marked for refilling.
     * @ORM\Column(type="boolean")
     */
    protected $needs_refill = false;

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

    /**
     * Check if the current part lot is expired.
     * This is the case, if the expiration date is greater the the current date.
     * @return bool|null True, if the part lot is expired. Returns null, if no expiration date was set.
     * @throws \Exception
     */
    public function isExpired(): ?bool
    {
        if ($this->expiration_date === null) {
            return null;
        }

        //Check if the expiration date is bigger then current time
        return $this->expiration_date < new \DateTime();
    }

    /**
     * Gets the description of the part lot. Similar to a "name" of the part lot.
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Sets the description of the part lot.
     * @param string $description
     * @return PartLot
     */
    public function setDescription(string $description): PartLot
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Gets the comment for this part lot.
     * @return string
     */
    public function getComment(): string
    {
        return $this->comment;
    }

    /**
     * Sets the comment for this part lot.
     * @param string $comment
     * @return PartLot
     */
    public function setComment(string $comment): PartLot
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * Gets the expiration date for the part lot. Returns null, if no expiration date was set.
     * @return \DateTime|null
     */
    public function getExpirationDate(): ?\DateTime
    {
        return $this->expiration_date;
    }

    /**
     * Sets the expiration date for the part lot. Set to null, if the part lot does not expires.
     * @param \DateTime $expiration_date
     * @return PartLot
     */
    public function setExpirationDate(?\DateTime $expiration_date): PartLot
    {
        $this->expiration_date = $expiration_date;
        return $this;
    }

    /**
     * Gets the storage locatiion, where this part lot is stored.
     * @return Storelocation|null The store location where this part is stored
     */
    public function getStorageLocation(): ?Storelocation
    {
        return $this->storage_location;
    }

    /**
     * Sets the storage location, where this part lot is stored
     * @param Storelocation|null $storage_location
     * @return PartLot
     */
    public function setStorageLocation(?Storelocation $storage_location): PartLot
    {
        $this->storage_location = $storage_location;
        return $this;
    }

    /**
     * Return the part that is stored in this part lot.
     * @return Part
     */
    public function getPart(): Part
    {
        return $this->part;
    }

    /**
     * Sets the part that is stored in this part lot.
     * @param Part $part
     * @return PartLot
     */
    public function setPart(Part $part): PartLot
    {
        $this->part = $part;
        return $this;
    }

    /**
     * Checks if the instock value in the part lot is unknown.
     *
     * @return bool
     */
    public function isInstockUnknown(): bool
    {
        return $this->instock_unknown;
    }

    /**
     * Set the unknown instock status of this part lot.
     * @param bool $instock_unknown
     * @return PartLot
     */
    public function setInstockUnknown(bool $instock_unknown): PartLot
    {
        $this->instock_unknown = $instock_unknown;
        return $this;
    }

    /**
     * @return float
     */
    public function getAmount(): float
    {
        if ($this->part instanceof Part && !$this->part->useFloatAmount()) {
            return round($this->amount);
        }
        return (float) $this->amount;
    }

    public function setAmount(float $new_amount): PartLot
    {
        $this->amount  = $new_amount;
        return $this;
    }



    /**
     * @return bool
     */
    public function isNeedsRefill(): bool
    {
        return $this->needs_refill;
    }

    /**
     * @param bool $needs_refill
     * @return PartLot
     */
    public function setNeedsRefill(bool $needs_refill): PartLot
    {
        $this->needs_refill = $needs_refill;
        return $this;
    }


}