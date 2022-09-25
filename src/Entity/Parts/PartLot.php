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

namespace App\Entity\Parts;

use App\Entity\Base\AbstractDBElement;
use App\Entity\Base\TimestampTrait;
use App\Entity\Contracts\NamedElementInterface;
use App\Entity\Contracts\TimeStampableInterface;
use App\Validator\Constraints\Selectable;
use App\Validator\Constraints\ValidPartLot;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity describes a lot where parts can be stored.
 * It is the connection between a part and its store locations.
 *
 * @ORM\Entity()
 * @ORM\Table(name="part_lots", indexes={
 *    @ORM\Index(name="part_lots_idx_instock_un_expiration_id_part", columns={"instock_unknown", "expiration_date", "id_part"}),
 *    @ORM\Index(name="part_lots_idx_needs_refill", columns={"needs_refill"}),
 * })
 * @ORM\HasLifecycleCallbacks()
 * @ValidPartLot()
 */
class PartLot extends AbstractDBElement implements TimeStampableInterface, NamedElementInterface
{
    use TimestampTrait;

    /**
     * @var string A short description about this lot, shown in table
     * @ORM\Column(type="text")
     */
    protected string $description = '';

    /**
     * @var string a comment stored with this lot
     * @ORM\Column(type="text")
     */
    protected string $comment = '';

    /**
     * @var ?DateTime Set a time until when the lot must be used.
     *                Set to null, if the lot can be used indefinitely.
     * @ORM\Column(type="datetime", name="expiration_date", nullable=true)
     */
    protected ?DateTime $expiration_date = null;

    /**
     * @var Storelocation|null The storelocation of this lot
     * @ORM\ManyToOne(targetEntity="Storelocation")
     * @ORM\JoinColumn(name="id_store_location", referencedColumnName="id", nullable=true)
     * @Selectable()
     */
    protected ?Storelocation $storage_location = null;

    /**
     * @var bool If this is set to true, the instock amount is marked as not known
     * @ORM\Column(type="boolean")
     */
    protected bool $instock_unknown = false;

    /**
     * @var float For continuous sizes (length, volume, etc.) the instock is saved here.
     * @ORM\Column(type="float")
     * @Assert\PositiveOrZero()
     */
    protected float $amount = 0.0;

    /**
     * @var bool determines if this lot was manually marked for refilling
     * @ORM\Column(type="boolean")
     */
    protected bool $needs_refill = false;

    /**
     * @var Part The part that is stored in this lot
     * @ORM\ManyToOne(targetEntity="Part", inversedBy="partLots")
     * @ORM\JoinColumn(name="id_part", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * @Assert\NotNull()
     */
    protected Part $part;

    public function __clone()
    {
        if ($this->id) {
            $this->addedDate = null;
        }
        parent::__clone();
    }

    /**
     * Check if the current part lot is expired.
     * This is the case, if the expiration date is greater the the current date.
     *
     * @return bool|null True, if the part lot is expired. Returns null, if no expiration date was set.
     *
     * @throws Exception If an error with the DateTime occurs
     */
    public function isExpired(): ?bool
    {
        if (null === $this->expiration_date) {
            return null;
        }

        //Check if the expiration date is bigger then current time
        return $this->expiration_date < new DateTime('now');
    }

    /**
     * Gets the description of the part lot. Similar to a "name" of the part lot.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Sets the description of the part lot.
     *
     * @return PartLot
     */
    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Gets the comment for this part lot.
     */
    public function getComment(): string
    {
        return $this->comment;
    }

    /**
     * Sets the comment for this part lot.
     *
     * @return PartLot
     */
    public function setComment(string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    /**
     * Gets the expiration date for the part lot. Returns null, if no expiration date was set.
     */
    public function getExpirationDate(): ?DateTime
    {
        return $this->expiration_date;
    }

    /**
     * Sets the expiration date for the part lot. Set to null, if the part lot does not expires.
     *
     * @param  DateTime|null  $expiration_date
     *
     * @return PartLot
     */
    public function setExpirationDate(?DateTime $expiration_date): self
    {
        $this->expiration_date = $expiration_date;

        return $this;
    }

    /**
     * Gets the storage location, where this part lot is stored.
     *
     * @return Storelocation|null The store location where this part is stored
     */
    public function getStorageLocation(): ?Storelocation
    {
        return $this->storage_location;
    }

    /**
     * Sets the storage location, where this part lot is stored.
     *
     * @return PartLot
     */
    public function setStorageLocation(?Storelocation $storage_location): self
    {
        $this->storage_location = $storage_location;

        return $this;
    }

    /**
     * Return the part that is stored in this part lot.
     */
    public function getPart(): Part
    {
        return $this->part;
    }

    /**
     * Sets the part that is stored in this part lot.
     *
     * @return PartLot
     */
    public function setPart(Part $part): self
    {
        $this->part = $part;

        return $this;
    }

    /**
     * Checks if the instock value in the part lot is unknown.
     */
    public function isInstockUnknown(): bool
    {
        return $this->instock_unknown;
    }

    /**
     * Set the unknown instock status of this part lot.
     *
     * @return PartLot
     */
    public function setInstockUnknown(bool $instock_unknown): self
    {
        $this->instock_unknown = $instock_unknown;

        return $this;
    }

    public function getAmount(): float
    {
        if ($this->part instanceof Part && !$this->part->useFloatAmount()) {
            return round($this->amount);
        }

        return (float) $this->amount;
    }

    /**
     * Sets the amount of parts in the part lot.
     * If null is passed, amount will be set to unknown.
     *
     * @return $this
     */
    public function setAmount(?float $new_amount): self
    {
        //Treat null like unknown amount
        if (null === $new_amount) {
            $this->instock_unknown = true;
            $new_amount = 0.0;
        }

        $this->amount = $new_amount;

        return $this;
    }

    public function isNeedsRefill(): bool
    {
        return $this->needs_refill;
    }

    /**
     * @return PartLot
     */
    public function setNeedsRefill(bool $needs_refill): self
    {
        $this->needs_refill = $needs_refill;

        return $this;
    }

    public function getName(): string
    {
        return $this->description;
    }
}
