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

namespace App\Entity\Parts;

use App\Entity\Attachments\SupplierAttachment;
use App\Entity\Base\AbstractCompany;
use App\Entity\Parameters\SupplierParameter;
use App\Entity\PriceInformations\Currency;
use App\Validator\Constraints\BigDecimal\BigDecimalPositiveOrZero;
use App\Validator\Constraints\Selectable;
use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Supplier.
 *
 * @ORM\Entity(repositoryClass="App\Repository\Parts\SupplierRepository")
 * @ORM\Table("`suppliers`")
 */
class Supplier extends AbstractCompany
{
    /**
     * @ORM\OneToMany(targetEntity="Supplier", mappedBy="parent")
     * @ORM\OrderBy({"name" = "ASC"})
     */
    protected $children;

    /**
     * @ORM\ManyToOne(targetEntity="Supplier", inversedBy="children")
     * @ORM\JoinColumn(name="parent_id", referencedColumnName="id")
     */
    protected $parent;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\PriceInformations\Orderdetail", mappedBy="supplier")
     */
    protected $orderdetails;

    /**
     * @var Currency|null The currency that should be used by default for order informations with this supplier.
     *                    Set to null, to use global base currency.
     * @ORM\ManyToOne(targetEntity="App\Entity\PriceInformations\Currency")
     * @ORM\JoinColumn(name="default_currency_id", referencedColumnName="id", nullable=true)
     * @Selectable()
     */
    protected $default_currency;

    /**
     * @var BigDecimal|null the shipping costs that have to be paid, when ordering via this supplier
     * @ORM\Column(name="shipping_costs", nullable=true, type="big_decimal", precision=11, scale=5)
     * @BigDecimalPositiveOrZero()
     */
    protected $shipping_costs;

    /**
     * @var Collection<int, SupplierAttachment>
     * @ORM\OneToMany(targetEntity="App\Entity\Attachments\SupplierAttachment", mappedBy="element", cascade={"persist", "remove"}, orphanRemoval=true)
     * @ORM\OrderBy({"name" = "ASC"})
     * @Assert\Valid()
     */
    protected $attachments;

    /** @var Collection<int, SupplierParameter>
     * @ORM\OneToMany(targetEntity="App\Entity\Parameters\SupplierParameter", mappedBy="element", cascade={"persist", "remove"}, orphanRemoval=true)
     * @ORM\OrderBy({"group" = "ASC" ,"name" = "ASC"})
     * @Assert\Valid()
     */
    protected $parameters;

    /**
     * Gets the currency that should be used by default, when creating a orderdetail with this supplier.
     *
     * @return Currency|null
     */
    public function getDefaultCurrency(): ?Currency
    {
        return $this->default_currency;
    }

    /**
     * Sets the default currency.
     *
     * @return Supplier
     */
    public function setDefaultCurrency(?Currency $default_currency): self
    {
        $this->default_currency = $default_currency;

        return $this;
    }

    /**
     * Gets the shipping costs for an order with this supplier, given in base currency.
     *
     * @return BigDecimal|null A BigDecimal with the shipping costs
     */
    public function getShippingCosts(): ?BigDecimal
    {
        return $this->shipping_costs;
    }

    /**
     * Sets the shipping costs for an order with this supplier.
     *
     * @param string|null $shipping_costs a BigDecimal with the shipping costs
     *
     * @return Supplier
     */
    public function setShippingCosts(?BigDecimal $shipping_costs): self
    {
        $this->shipping_costs = $shipping_costs;
        return $this;
    }
}
