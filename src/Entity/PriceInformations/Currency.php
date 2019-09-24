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

namespace App\Entity\PriceInformations;


use App\Entity\Attachments\CurrencyAttachment;
use App\Entity\Attachments\SupplierAttachment;
use App\Entity\Base\StructuralDBElement;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity describes a currency that can be used for price informations.
 * @package App\Entity
 * @UniqueEntity("iso_code")
 * @ORM\Entity()
 * @ORM\Table(name="currencies")
 */
class Currency extends StructuralDBElement
{

    public const PRICE_SCALE = 5;

    /**
     * @var Collection|CurrencyAttachment[]
     * @ORM\OneToMany(targetEntity="App\Entity\Attachments\CurrencyAttachment", mappedBy="element", cascade={"persist", "remove"}, orphanRemoval=true)
     */
    protected $attachments;

    /**
     * @var string The 3 letter ISO code of the currency.
     * @ORM\Column(type="string")
     * @Assert\Currency()
     */
    protected $iso_code;

    /**
     * @var string|null The exchange rate between this currency and the base currency
     * (how many base units the current currency is worth)
     * @ORM\Column(type="decimal", precision=11, scale=5, nullable=true)
     * @Assert\Positive()
     */
    protected $exchange_rate;

    /**
     * @ORM\OneToMany(targetEntity="Currency", mappedBy="parent", cascade={"persist"})
     */
    protected $children;

    /**
     * @ORM\ManyToOne(targetEntity="Currency", inversedBy="children")
     * @ORM\JoinColumn(name="parent_id", referencedColumnName="id")
     */
    protected $parent;

    /**
     * Returns the 3 letter ISO code of this currency
     * @return string
     */
    public function getIsoCode(): ?string
    {
        return $this->iso_code;
    }

    /**
     * @param string $iso_code
     * @return Currency
     */
    public function setIsoCode(?string $iso_code): Currency
    {
        $this->iso_code = $iso_code;
        return $this;
    }

    /**
     * Returns the inverse exchange rate (how many of the current currency the base unit is worth)
     * @return string|null
     */
    public function getInverseExchangeRate(): ?string
    {
        $tmp = $this->getExchangeRate();

        if ($tmp === null || (float) $tmp === 0) {
            return null;
        }

        return bcdiv(1, $tmp, static::PRICE_SCALE);
    }

    /**
     * Returns The exchange rate between this currency and the base currency
     * (how many base units the current currency is worth)
     * @return string|null
     */
    public function getExchangeRate(): ?string
    {
        return $this->exchange_rate;
    }

    /**
     * @param string|null $exchange_rate
     * @return Currency
     */
    public function setExchangeRate(?string $exchange_rate): Currency
    {
        $this->exchange_rate = $exchange_rate;
        return $this;
    }


    /**
     * Returns the ID as an string, defined by the element class.
     * This should have a form like P000014, for a part with ID 14.
     *
     * @return string The ID as a string;
     *
     */
    public function getIDString(): string
    {
        return 'C' . $this->getID();
    }
}