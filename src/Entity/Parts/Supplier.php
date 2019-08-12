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

namespace App\Entity\Parts;

use App\Entity\Base\Company;
use App\Entity\PriceInformations\Currency;
use App\Validator\Constraints\Selectable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Supplier.
 *
 * @ORM\Entity(repositoryClass="App\Repository\StructuralDBElementRepository")
 * @ORM\Table("`suppliers`")
 */
class Supplier extends Company
{
    /**
     * @ORM\OneToMany(targetEntity="Supplier", mappedBy="parent")
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
     * Set to null, to use global base currency.
     * @ORM\ManyToOne(targetEntity="App\Entity\PriceInformations\Currency")
     * @ORM\JoinColumn(name="default_currency_id", referencedColumnName="id", nullable=true)
     * @Selectable()
     */
    protected $default_currency;

    /**
     * @var float|null The shipping costs that have to be paid, when ordering via this supplier.
     * @ORM\Column(name="shipping_costs", nullable=true, type="decimal")
     * @Assert\PositiveOrZero()
     */
    protected $shipping_costs;

    /**
     * @return ?Currency
     */
    public function getDefaultCurrency()
    {
        return $this->default_currency;
    }

    /**
     * @param ?Currency $default_currency
     * @return Supplier
     */
    public function setDefaultCurrency(?Currency $default_currency) : Supplier
    {
        $this->default_currency = $default_currency;
        return $this;
    }

    /**
     * @return ?float
     */
    public function getShippingCosts() : ?float
    {
        return $this->shipping_costs;
    }

    /**
     * @param ?float $shipping_costs
     * @return Supplier
     */
    public function setShippingCosts(?float $shipping_costs) : Supplier
    {
        $this->shipping_costs = $shipping_costs;
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
        return 'L'.sprintf('%06d', $this->getID());
    }
}
