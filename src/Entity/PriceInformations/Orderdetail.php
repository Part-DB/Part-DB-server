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

namespace App\Entity\PriceInformations;

use App\Entity\Base\AbstractDBElement;
use App\Entity\Base\TimestampTrait;
use App\Entity\Contracts\TimeStampableInterface;
use App\Entity\Parts\Part;
use App\Entity\Parts\Supplier;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Orderdetail.
 *
 * @ORM\Table("`orderdetails`")
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks()
 */
class Orderdetail extends AbstractDBElement implements TimeStampableInterface
{
    use TimestampTrait;

    /**
     * @ORM\OneToMany(targetEntity="Pricedetail", mappedBy="orderdetail", cascade={"persist", "remove"}, orphanRemoval=true)
     * @Assert\Valid()
     * @ORM\OrderBy({"min_discount_quantity" = "ASC"})
     */
    protected $pricedetails;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    protected $supplierpartnr = '';

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    protected $obsolete = false;

    /**
     * @var string
     * @ORM\Column(type="string")
     * @Assert\Url()
     */
    protected $supplier_product_url = '';

    /**
     * @var Part
     * @ORM\ManyToOne(targetEntity="App\Entity\Parts\Part", inversedBy="orderdetails")
     * @ORM\JoinColumn(name="part_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * @Assert\NotNull()
     */
    protected $part;

    /**
     * @var Supplier
     * @ORM\ManyToOne(targetEntity="App\Entity\Parts\Supplier", inversedBy="orderdetails")
     * @ORM\JoinColumn(name="id_supplier", referencedColumnName="id")
     */
    protected $supplier;

    public function __construct()
    {
        $this->pricedetails = new ArrayCollection();
    }

    public function __clone()
    {
        if ($this->id) {
            $this->addedDate = null;
            $pricedetails = $this->pricedetails;
            $this->pricedetails = new ArrayCollection();
            //Set master attachment is needed
            foreach ($pricedetails as $pricedetail) {
                $this->addPricedetail(clone $pricedetail);
            }
        }
        parent::__clone();
    }

    /**
     * Returns the ID as an string, defined by the element class.
     * This should have a form like P000014, for a part with ID 14.
     *
     * @return string The ID as a string;
     */
    public function getIDString(): string
    {
        return 'O'.sprintf('%06d', $this->getID());
    }

    /********************************************************************************
     *
     *   Getters
     *
     *********************************************************************************/

    /**
     * Get the part.
     *
     * @return Part|null the part of this orderdetails
     */
    public function getPart(): ?Part
    {
        return $this->part;
    }

    /**
     * Get the supplier.
     *
     * @return Supplier the supplier of this orderdetails
     */
    public function getSupplier(): ?Supplier
    {
        return $this->supplier;
    }

    /**
     * Get the supplier part-nr.
     *
     * @return string the part-nr
     */
    public function getSupplierPartNr(): string
    {
        return $this->supplierpartnr;
    }

    /**
     * Get if this orderdetails is obsolete.
     *
     * "Orderdetails is obsolete" means that the part with that supplier-part-nr
     * is no longer available from the supplier of that orderdetails.
     *
     * @return bool * true if this part is obsolete at that supplier
     *              * false if this part isn't obsolete at that supplier
     */
    public function getObsolete(): bool
    {
        return (bool) $this->obsolete;
    }

    /**
     * Get the link to the website of the article on the suppliers website.
     *
     * @param bool $no_automatic_url Set this to true, if you only want to get the local set product URL for this Orderdetail
     *                               and not a automatic generated one, based from the Supplier
     *
     * @return string the link to the article
     */
    public function getSupplierProductUrl(bool $no_automatic_url = false): string
    {
        if ($no_automatic_url || '' !== $this->supplier_product_url) {
            return $this->supplier_product_url;
        }

        if (null === $this->getSupplier()) {
            return '';
        }

        return $this->getSupplier()->getAutoProductUrl($this->supplierpartnr); // maybe an automatic url is available...
    }

    /**
     * Get all pricedetails.
     *
     * @return Pricedetail[]|Collection all pricedetails as a one-dimensional array of Pricedetails objects,
     *                                  sorted by minimum discount quantity
     */
    public function getPricedetails(): Collection
    {
        return $this->pricedetails;
    }

    /**
     * Adds an pricedetail to this orderdetail.
     *
     * @param Pricedetail $pricedetail The pricedetail to add
     *
     * @return Orderdetail
     */
    public function addPricedetail(Pricedetail $pricedetail): self
    {
        $pricedetail->setOrderdetail($this);
        $this->pricedetails->add($pricedetail);

        return $this;
    }

    /**
     * Removes an pricedetail from this orderdetail.
     *
     * @return Orderdetail
     */
    public function removePricedetail(Pricedetail $pricedetail): self
    {
        $this->pricedetails->removeElement($pricedetail);

        return $this;
    }

    /**
     * Find the pricedetail that is correct for the desired amount (the one with the greatest discount value with a
     * minimum order amount of the wished quantity).
     *
     * @param float $quantity this is the quantity to choose the correct pricedetails
     *
     * @return Pricedetail|null: the price as a bcmath string. Null if there are no orderdetails for the given quantity
     */
    public function findPriceForQty(float $quantity = 1.0): ?Pricedetail
    {
        if ($quantity <= 0) {
            return null;
        }

        $all_pricedetails = $this->getPricedetails();

        $correct_pricedetail = null;
        foreach ($all_pricedetails as $pricedetail) {
            // choose the correct pricedetails for the chosen quantity ($quantity)
            if ($quantity < $pricedetail->getMinDiscountQuantity()) {
                break;
            }

            $correct_pricedetail = $pricedetail;
        }

        return $correct_pricedetail;
    }

    /********************************************************************************
     *
     *   Setters
     *
     *********************************************************************************/

    /**
     * Sets a new part with which this orderdetail is associated.
     *
     * @return Orderdetail
     */
    public function setPart(Part $part): self
    {
        $this->part = $part;

        return $this;
    }

    /**
     * Sets the new supplier associated with this orderdetail.
     *
     * @return Orderdetail
     */
    public function setSupplier(Supplier $new_supplier): self
    {
        $this->supplier = $new_supplier;

        return $this;
    }

    /**
     * Set the supplier part-nr.
     *
     * @param string $new_supplierpartnr the new supplier-part-nr
     *
     * @return Orderdetail
     * @return Orderdetail
     */
    public function setSupplierpartnr(string $new_supplierpartnr): self
    {
        $this->supplierpartnr = $new_supplierpartnr;

        return $this;
    }

    /**
     * Set if the part is obsolete at the supplier of that orderdetails.
     *
     * @param bool $new_obsolete true means that this part is obsolete
     *
     * @return Orderdetail
     * @return Orderdetail
     */
    public function setObsolete(bool $new_obsolete): self
    {
        $this->obsolete = $new_obsolete;

        return $this;
    }

    /**
     * Sets the custom product supplier URL for this order detail.
     * Set this to "", if the function getSupplierProductURL should return the automatic generated URL.
     *
     * @param string $new_url The new URL for the supplier URL
     *
     * @return Orderdetail
     */
    public function setSupplierProductUrl(string $new_url): self
    {
        //Only change the internal URL if it is not the auto generated one
        if ($new_url === $this->supplier->getAutoProductUrl($this->getSupplierPartNr())) {
            return $this;
        }

        $this->supplier_product_url = $new_url;

        return $this;
    }
}
