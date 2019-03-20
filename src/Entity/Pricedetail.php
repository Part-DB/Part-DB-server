<?php declare(strict_types=1);

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


namespace App\Entity;


use Doctrine\ORM\Mapping as ORM;
use Webmozart\Assert\Assert;

/**
 * Class Pricedetail
 * @package App\Entity
 *
 * @ORM\Entity()
 * @ORM\Table("pricedetails")
 */
class Pricedetail extends  DBElement
{
    /**
     * @var Orderdetail
     * @ORM\ManyToOne(targetEntity="Orderdetail", inversedBy="pricedetails")
     * @ORM\JoinColumn(name="orderdetails_id", referencedColumnName="id")
     */
    protected $orderdetail;

    /**
     * @var float
     * @ORM\Column(type="decimal", precision=11, scale=5)
     */
    protected $price;

    /**
     * @var int
     * @ORM\Column(type="integer")
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
     * Get the orderdetails of this pricedetails
     *
     * @return Orderdetail     the orderdetails object
     *
     */
    public function getOrderdetails() : Orderdetail
    {
        return $this->orderdetail;
    }

    /**
     * Get the price
     *
     * @param boolean $as_money_string      * if true, this method returns a money string incl. currency
     *                                      * if false, this method returns the price as float
     * @param integer $multiplier           The returned price (float or string) will be multiplied
     *                                      with this multiplier.
     *
     *     You will get the price for $multiplier parts. If you want the price which is stored
     *          in the database, you have to pass the "price_related_quantity" count as $multiplier.
     *
     * @return float    the price as a float number (if "$as_money_string == false")
     * @return string   the price as a string incl. currency (if "$as_money_string == true")
     *
     * @see floatToMoneyString()
     */
    public function getPrice(bool $as_money_string = false, int $multiplier = 1)
    {
        $price = ($this->price * $multiplier) / $this->price_related_quantity;

        if ($as_money_string) {
            throw new \Exception('Not implemented yet...');
            //return floatToMoneyString($price);
        }

        return $price;
    }

    /**
     *  Get the price related quantity
     *
     * This is the quantity, for which the price is valid.
     *
     * @return integer       the price related quantity
     *
     * @see Pricedetails::setPriceRelatedQuantity()
     */
    public function getPriceRelatedQuantity() : int
    {
        return $this->price_related_quantity;
    }

    /**
     *  Get the minimum discount quantity
     *
     * "Minimum discount quantity" means the minimum order quantity for which the price
     * of this orderdetails is valid.
     *
     * @return integer       the minimum discount quantity
     *
     * @see Pricedetails::setMinDiscountQuantity()
     */
    public function getMinDiscountQuantity() : int
    {
        return $this->min_discount_quantity;
    }

    /********************************************************************************
     *
     *   Setters
     *
     *********************************************************************************/

    /**
     *  Set the price
     *
     * @param float $new_price       the new price as a float number
     *
     *      * This is the price for "price_related_quantity" parts!!
     *              * Example: if "price_related_quantity" is '10',
     *                  you have to set here the price for 10 parts!
     *
     * @return self
     */
    public function setPrice(float $new_price) : self
    {
        Assert::natural($new_price, 'The new price must be positive! Got %s!');

        $this->price = $new_price;

        return $this;
    }

    /**
     *  Set the price related quantity
     *
     * This is the quantity, for which the price is valid.
     *
     * @par Example:
     * If 100pcs costs 20$, you have to set the price to 20$ and the price related
     * quantity to 100. The single price (20$/100 = 0.2$) will be calculated automatically.
     *
     * @param integer $new_price_related_quantity the price related quantity
     *
     * @return self
     */
    public function setPriceRelatedQuantity(int $new_price_related_quantity) : self
    {

        Assert::greaterThan($new_price_related_quantity, 0,
            'The new price related quantity must be greater zero! Got %s.');

        $this->price_related_quantity = $new_price_related_quantity;

        return $this;
    }

    /**
     *  Set the minimum discount quantity
     *
     * "Minimum discount quantity" means the minimum order quantity for which the price
     * of this orderdetails is valid. This way, you're able to use different prices
     * for different order quantities (quantity discount!).
     *
     * @par Example:
     *      - 1-9pcs costs 10$: set price to 10$/pcs and minimum discount quantity to 1
     *      - 10-99pcs costs 9$: set price to 9$/pcs and minimum discount quantity to 10
     *      - 100pcs or more costs 8$: set price/pcs to 8$ and minimum discount quantity to 100
     *
     * (Each of this examples would be an own Pricedetails-object.
     * So the orderdetails would have three Pricedetails for one supplier.)
     *
     * @param integer $new_min_discount_quantity the minimum discount quantity
     *
     * @return self
     */
    public function setMinDiscountQuantity(int $new_min_discount_quantity) : self
    {
        Assert::greaterThan($new_min_discount_quantity, 0,
            'The new minimum discount quantity must be greater zero! Got %s.');

        $this->min_discount_quantity = $new_min_discount_quantity;

        return $this;
    }




    /**
     * Returns the ID as an string, defined by the element class.
     * This should have a form like P000014, for a part with ID 14.
     * @return string The ID as a string;
     */
    public function getIDString(): string
    {
        return 'PD' . sprintf('%06d', $this->getID());
    }
}