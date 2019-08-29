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

use App\Entity\Base\DBElement;
use App\Entity\Base\TimestampTrait;
use App\Entity\Parts\Part;
use App\Entity\Parts\Supplier;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\PersistentCollection;
use Exception;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Orderdetail.
 *
 * @ORM\Table("`orderdetails`")
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks()
 */
class Orderdetail extends DBElement
{
    use TimestampTrait;

    /**
     * @var Part
     * @ORM\ManyToOne(targetEntity="App\Entity\Parts\Part", inversedBy="orderdetails")
     * @ORM\JoinColumn(name="part_id", referencedColumnName="id")
     */
    protected $part;

    /**
     * @var Supplier
     * @ORM\ManyToOne(targetEntity="App\Entity\Parts\Supplier", inversedBy="orderdetails")
     * @ORM\JoinColumn(name="id_supplier", referencedColumnName="id")
     */
    protected $supplier;

    /**
     * @ORM\OneToMany(targetEntity="Pricedetail", mappedBy="orderdetail")
     */
    protected $pricedetails;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    protected $supplierpartnr = "";

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
    protected $supplier_product_url = "";

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
     * @return Part the part of this orderdetails
     */
    public function getPart(): Part
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
     * @return string the part-nr.
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
     * @param $no_automatic_url bool Set this to true, if you only want to get the local set product URL for this Orderdetail
     * and not a automatic generated one, based from the Supplier
     *
     * @return string the link to the article
     */
    public function getSupplierProductUrl(bool $no_automatic_url = false): string
    {
        if ($no_automatic_url || '' !== $this->supplier_product_url) {
            return $this->supplier_product_url;
        }

        return $this->getSupplier()->getAutoProductUrl($this->supplierpartnr); // maybe an automatic url is available...
    }

    /**
     * Get all pricedetails.
     *
     * @return Pricedetail[] all pricedetails as a one-dimensional array of Pricedetails objects,
     *                        sorted by minimum discount quantity
     *
     * @throws Exception if there was an error
     */
    public function getPricedetails(): PersistentCollection
    {
        return $this->pricedetails;
    }

    /**
     * Get the price for a specific quantity.
     * @param int      $quantity        this is the quantity to choose the correct pricedetails
     * @param int|null $multiplier      * This is the multiplier which will be applied to every single price
     *                                  * If you pass NULL, the number from $quantity will be used
     *
     * @return float float: the price as a float number (if "$as_money_string == false")
     *
     * @throws Exception if there are no pricedetails for the choosed quantity
     *                   (for example, there are only one pricedetails with the minimum discount quantity '10',
     *                   but the choosed quantity is '5' --> the price for 5 parts is not defined!)
     * @throws Exception if there was an error
     */
    public function getPrice(int $quantity = 1, $multiplier = null) : ?float
    {

        if (($quantity === 0) && ($multiplier === null)) {
                return 0.0;
        }

        $all_pricedetails = $this->getPricedetails();

        if (count($all_pricedetails) == 0) {
                return null;
        }


        $correct_pricedetails = null;
        foreach ($all_pricedetails as $pricedetails) {
            // choose the correct pricedetails for the choosed quantity ($quantity)
            if ($quantity < $pricedetails->getMinDiscountQuantity()) {
                break;
            }

            $correct_pricedetails = $pricedetails;
        }

        if ($correct_pricedetails === null) {
            return null;
        }

        if ($multiplier === null) {
            $multiplier = $quantity;
        }

        return $correct_pricedetails->getPricePerUnit($multiplier);
    }

    /********************************************************************************
     *
     *   Setters
     *
     *********************************************************************************/

    /**
     * Sets the new supplier associated with this orderdetail.
     * @param Supplier $new_supplier
     * @return Orderdetail
     */
    public function setSupplier(Supplier $new_supplier) : Orderdetail
    {
        $this->supplier = $new_supplier;
        return $this;
    }

    /**
     * Set the supplier part-nr.
     * @param string $new_supplierpartnr the new supplier-part-nr
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
     * @param bool $new_obsolete true means that this part is obsolete
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
     * @param $new_url string The new URL for the supplier URL.
     * @return Orderdetail
     * @return Orderdetail
     */
    public function setSupplierProductUrl(string $new_url)
    {
        $this->supplier_product_url = $new_url;

        return $this;
    }
}
