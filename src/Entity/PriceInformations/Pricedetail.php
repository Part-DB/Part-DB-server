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
use App\Validator\Constraints\Selectable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Pricedetail.
 *
 * @ORM\Entity()
 * @ORM\Table("`pricedetails`")
 * @ORM\HasLifecycleCallbacks()
 * @UniqueEntity(fields={"orderdetail", "min_discount_quantity"})
 */
class Pricedetail extends AbstractDBElement
{
    use TimestampTrait;

    public const PRICE_PRECISION = 5;

    /**
     * @var string The price related to the detail. (Given in the selected currency)
     * @ORM\Column(type="decimal", precision=11, scale=5)
     * @Assert\Positive()
     */
    protected $price = '0.0';

    /**
     * @var ?Currency The currency used for the current price information.
     *                If this is null, the global base unit is assumed.
     * @ORM\ManyToOne(targetEntity="Currency")
     * @ORM\JoinColumn(name="id_currency", referencedColumnName="id", nullable=true)
     * @Selectable()
     */
    protected $currency;

    /**
     * @var float
     * @ORM\Column(type="float")
     * @Assert\Positive()
     */
    protected $price_related_quantity = 1.0;

    /**
     * @var float
     * @ORM\Column(type="float")
     * @Assert\Positive()
     */
    protected $min_discount_quantity = 1.0;

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    protected $manual_input = true;

    /**
     * @var Orderdetail|null
     * @ORM\ManyToOne(targetEntity="Orderdetail", inversedBy="pricedetails")
     * @ORM\JoinColumn(name="orderdetails_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     * @Assert\NotNull()
     */
    protected $orderdetail;

    public function __construct()
    {
        bcscale(static::PRICE_PRECISION);
    }

    /********************************************************************************
     *
     *   Getters
     *
     *********************************************************************************/

    /**
     * Get the orderdetail to which this pricedetail belongs to this pricedetails.
     *
     * @return Orderdetail the orderdetail this price belongs to
     */
    public function getOrderdetail(): Orderdetail
    {
        return $this->orderdetail;
    }

    /**
     * Returns the price associated with this pricedetail.
     * It is given in current currency and for the price related quantity.
     *
     * @return string the price as string, like returned raw from DB
     */
    public function getPrice(): string
    {
        return $this->price;
    }

    /**
     * Get the price for a single unit in the currency associated with this price detail.
     *
     * @param float|string $multiplier The returned price (float or string) will be multiplied
     *                                 with this multiplier.
     *
     *     You will get the price for $multiplier parts. If you want the price which is stored
     *          in the database, you have to pass the "price_related_quantity" count as $multiplier.
     *
     * @return string the price as a bcmath string
     */
    public function getPricePerUnit($multiplier = 1.0): string
    {
        $multiplier = (string) $multiplier;
        $tmp = bcmul($this->price, $multiplier, static::PRICE_PRECISION);

        return bcdiv($tmp, (string) $this->price_related_quantity, static::PRICE_PRECISION);
        //return ($this->price * $multiplier) / $this->price_related_quantity;
    }

    /**
     *  Get the price related quantity.
     *
     * This is the quantity, for which the price is valid.
     * The amount is measured in part unit.
     *
     * @return float the price related quantity
     *
     * @see Pricedetail::setPriceRelatedQuantity()
     */
    public function getPriceRelatedQuantity(): float
    {
        if ($this->orderdetail && $this->orderdetail->getPart() && ! $this->orderdetail->getPart()->useFloatAmount()) {
            $tmp = round($this->price_related_quantity);

            return $tmp < 1 ? 1 : $tmp;
        }

        return $this->price_related_quantity;
    }

    /**
     *  Get the minimum discount quantity.
     *
     * "Minimum discount quantity" means the minimum order quantity for which the price
     * of this orderdetails is valid.
     *
     * The amount is measured in part unit.
     *
     * @return float the minimum discount quantity
     *
     * @see Pricedetail::setMinDiscountQuantity()
     */
    public function getMinDiscountQuantity(): float
    {
        if ($this->orderdetail && $this->orderdetail->getPart() && ! $this->orderdetail->getPart()->useFloatAmount()) {
            $tmp = round($this->min_discount_quantity);

            return $tmp < 1 ? 1 : $tmp;
        }

        return $this->min_discount_quantity;
    }

    /**
     * Returns the currency associated with this price information.
     * Returns null, if no specific currency is selected and the global base currency should be assumed.
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
     * Sets the orderdetail to which this pricedetail belongs to.
     *
     * @param  Orderdetail  $orderdetail
     * @return $this
     */
    public function setOrderdetail(Orderdetail $orderdetail): self
    {
        $this->orderdetail = $orderdetail;

        return $this;
    }

    /**
     * Sets the currency associated with the price informations.
     * Set to null, to use the global base currency.
     *
     * @param  Currency|null  $currency
     * @return Pricedetail
     */
    public function setCurrency(?Currency $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     *  Set the price.
     *
     * @param string $new_price the new price as a float number
     *
     *      * This is the price for "price_related_quantity" parts!!
     *              * Example: if "price_related_quantity" is '10',
     *                  you have to set here the price for 10 parts!
     * @return $this
     */
    public function setPrice(string $new_price): self
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
     * @param float $new_price_related_quantity the price related quantity
     *
     * @return $this
     */
    public function setPriceRelatedQuantity(float $new_price_related_quantity): self
    {
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
     * @param float $new_min_discount_quantity the minimum discount quantity
     * @return $this
     */
    public function setMinDiscountQuantity(float $new_min_discount_quantity): self
    {
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
