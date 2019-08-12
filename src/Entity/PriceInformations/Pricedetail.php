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
use App\Validator\Constraints\Selectable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Pricedetail.
 *
 * @ORM\Entity()
 * @ORM\Table("`pricedetails`")
 * @UniqueEntity(fields={"orderdetail", "min_discount_quantity"})
 */
class Pricedetail extends DBElement
{
    /**
     * @var Orderdetail
     * @ORM\ManyToOne(targetEntity="Orderdetail", inversedBy="pricedetails")
     * @ORM\JoinColumn(name="orderdetails_id", referencedColumnName="id")
     */
    protected $orderdetail;

    /**
     * @var float The price related to the detail. (Given in the selected currency)
     * @ORM\Column(type="decimal", precision=11, scale=5)
     * @Assert\Positive()
     */
    protected $price;

    /**
     * @var ?Currency The currency used for the current price information.
     * If this is null, the global base unit is assumed.
     * @ORM\ManyToOne(targetEntity="Currency")
     * @ORM\JoinColumn(name="id_currency", referencedColumnName="id", nullable=true)
     * @Selectable()
     */
    protected $currency;

    /**
     * @var int
     * @ORM\Column(type="integer")
     * @Assert\Positive()
     */
    protected $price_related_quantity;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    protected $min_discount_quantity;

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    protected $manual_input;

    /**
     * @ORM\Column(type="datetimetz")
     */
    protected $last_modified;

    /********************************************************************************
     *
     *   Getters
     *
     *********************************************************************************/

    /**
     * Get the orderdetails of this pricedetails.
     *
     * @return Orderdetail the orderdetails object
     */
    public function getOrderdetails(): Orderdetail
    {
        return $this->orderdetail;
    }

    /**
     * Returns the price associated with this pricedetail.
     * It is given in current currency and for the price related quantity.
     * @return float
     */
    public function getPrice() : float
    {
        return (float) $this->price;
    }

    /**
     * Get the price for a single unit in the currency associated with this price detail.
     *
     * @param int  $multiplier      The returned price (float or string) will be multiplied
     *                              with this multiplier.
     *
     *     You will get the price for $multiplier parts. If you want the price which is stored
     *          in the database, you have to pass the "price_related_quantity" count as $multiplier.
     *
     * @return float  the price as a float number

     */
    public function getPricePerUnit(int $multiplier = 1) : float
    {
        return ($this->price * $multiplier) / $this->price_related_quantity;
    }

    /**
     *  Get the price related quantity.
     *
     * This is the quantity, for which the price is valid.
     *
     * @return int the price related quantity
     *
     * @see Pricedetail::setPriceRelatedQuantity()
     */
    public function getPriceRelatedQuantity(): int
    {
        return $this->price_related_quantity;
    }

    /**
     *  Get the minimum discount quantity.
     *
     * "Minimum discount quantity" means the minimum order quantity for which the price
     * of this orderdetails is valid.
     *
     * @return int the minimum discount quantity
     *
     * @see Pricedetail::setMinDiscountQuantity()
     */
    public function getMinDiscountQuantity(): int
    {
        return $this->min_discount_quantity;
    }

    /**
     * Returns the currency associated with this price information.
     * Returns null, if no specific currency is selected and the global base currency should be assumed.
     * @return Currency|null
     */
    public function getCurrency(): ?Currency
    {
        return $this->currency;
    }

    /********************************************************************************
     *
     *   Setters
     *
     *********************************************************************************/

    /**
     * Sets the currency associated with the price informations.
     * Set to null, to use the global base currency.
     * @param Currency|null $currency
     * @return Pricedetail
     */
    public function setCurrency(?Currency $currency): Pricedetail
    {
        $this->currency = $currency;
    }

    /**
     *  Set the price.
     *
     * @param float $new_price the new price as a float number
     *
     *      * This is the price for "price_related_quantity" parts!!
     *              * Example: if "price_related_quantity" is '10',
     *                  you have to set here the price for 10 parts!
     *
     * @return self
     */
    public function setPrice(float $new_price): Pricedetail
    {
        //Assert::natural($new_price, 'The new price must be positive! Got %s!');

        $this->price = $new_price;

        return $this;
    }

    /**
     *  Set the price related quantity.
     *
     * This is the quantity, for which the price is valid.
     *
     * Example:
     * If 100pcs costs 20$, you have to set the price to 20$ and the price related
     * quantity to 100. The single price (20$/100 = 0.2$) will be calculated automatically.
     *
     * @param int $new_price_related_quantity the price related quantity
     *
     * @return self
     */
    public function setPriceRelatedQuantity(int $new_price_related_quantity): self
    {
        //Assert::greaterThan($new_price_related_quantity, 0,
        //    'The new price related quantity must be greater zero! Got %s.');

        $this->price_related_quantity = $new_price_related_quantity;

        return $this;
    }

    /**
     *  Set the minimum discount quantity.
     *
     * "Minimum discount quantity" means the minimum order quantity for which the price
     * of this orderdetails is valid. This way, you're able to use different prices
     * for different order quantities (quantity discount!).
     *
     *  Example:
     *      - 1-9pcs costs 10$: set price to 10$/pcs and minimum discount quantity to 1
     *      - 10-99pcs costs 9$: set price to 9$/pcs and minimum discount quantity to 10
     *      - 100pcs or more costs 8$: set price/pcs to 8$ and minimum discount quantity to 100
     *
     * (Each of this examples would be an own Pricedetails-object.
     * So the orderdetails would have three Pricedetails for one supplier.)
     *
     * @param int $new_min_discount_quantity the minimum discount quantity
     *
     * @return self
     */
    public function setMinDiscountQuantity(int $new_min_discount_quantity): self
    {
        //Assert::greaterThan($new_min_discount_quantity, 0,
        //    'The new minimum discount quantity must be greater zero! Got %s.');

        $this->min_discount_quantity = $new_min_discount_quantity;

        return $this;
    }

    /**
     * Returns the ID as an string, defined by the element class.
     * This should have a form like P000014, for a part with ID 14.
     *
     * @return string The ID as a string;
     */
    public function getIDString(): string
    {
        return 'PD'.sprintf('%06d', $this->getID());
    }
}
