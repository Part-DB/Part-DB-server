<?php
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
use App\Entity\PriceInformations\Currency;
use App\Validator\Constraints\Selectable;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Supplier.
 *
 * @ORM\Entity(repositoryClass="App\Repository\StructuralDBElementRepository")
 * @ORM\Table("`suppliers`")
 */
class Supplier extends AbstractCompany
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
     *                    Set to null, to use global base currency.
     * @ORM\ManyToOne(targetEntity="App\Entity\PriceInformations\Currency")
     * @ORM\JoinColumn(name="default_currency_id", referencedColumnName="id", nullable=true)
     * @Selectable()
     */
    protected $default_currency;

    /**
     * @var string|null the shipping costs that have to be paid, when ordering via this supplier
     * @ORM\Column(name="shipping_costs", nullable=true, type="decimal", precision=11, scale=5)
     * @Assert\PositiveOrZero()
     */
    protected $shipping_costs;

    /**
     * @ORM\ManyToMany(targetEntity="Part", fetch="EXTRA_LAZY")
     * @ORM\JoinTable(name="orderdetails",
     *     joinColumns={@ORM\JoinColumn(name="id_supplier", referencedColumnName="id")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="part_id", referencedColumnName="id")}
     * )
     */
    protected $parts;
    /**
     * @var Collection|SupplierAttachment[]
     * @ORM\OneToMany(targetEntity="App\Entity\Attachments\SupplierAttachment", mappedBy="element", cascade={"persist", "remove"}, orphanRemoval=true)
     */
    protected $attachments;

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
     * @return string|null A bcmath string with the shipping costs
     */
    public function getShippingCosts(): ?string
    {
        return $this->shipping_costs;
    }

    /**
     * Sets the shipping costs for an order with this supplier.
     *
     * @param string|null $shipping_costs a bcmath string with the shipping costs
     *
     * @return Supplier
     */
    public function setShippingCosts(?string $shipping_costs): self
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
