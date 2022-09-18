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

namespace App\Entity\PriceInformations;

use App\Entity\Attachments\CurrencyAttachment;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Parameters\CurrencyParameter;
use App\Validator\Constraints\BigDecimal\BigDecimalPositive;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity describes a currency that can be used for price informations.
 *
 * @UniqueEntity("iso_code")
 * @ORM\Entity()
 * @ORM\Table(name="currencies")
 */
class Currency extends AbstractStructuralDBElement
{
    public const PRICE_SCALE = 5;

    /**
     * @var BigDecimal|null The exchange rate between this currency and the base currency
     *                      (how many base units the current currency is worth)
     * @ORM\Column(type="big_decimal", precision=11, scale=5, nullable=true)
     * @BigDecimalPositive()
     */
    protected ?BigDecimal $exchange_rate = null;

    /**
     * @var string the 3 letter ISO code of the currency
     * @ORM\Column(type="string")
     * @Assert\Currency()
     */
    protected string $iso_code;

    /**
     * @ORM\OneToMany(targetEntity="Currency", mappedBy="parent", cascade={"persist"})
     * @ORM\OrderBy({"name" = "ASC"})
     */
    protected $children;

    /**
     * @ORM\ManyToOne(targetEntity="Currency", inversedBy="children")
     * @ORM\JoinColumn(name="parent_id", referencedColumnName="id")
     */
    protected $parent;

    /**
     * @var Collection<int, CurrencyAttachment>
     * @ORM\OneToMany(targetEntity="App\Entity\Attachments\CurrencyAttachment", mappedBy="element", cascade={"persist", "remove"}, orphanRemoval=true)
     * @ORM\OrderBy({"name" = "ASC"})
     * @Assert\Valid()
     */
    protected $attachments;

    /** @var Collection<int, CurrencyParameter>
     * @ORM\OneToMany(targetEntity="App\Entity\Parameters\CurrencyParameter", mappedBy="element", cascade={"persist", "remove"}, orphanRemoval=true)
     * @ORM\OrderBy({"group" = "ASC" ,"name" = "ASC"})
     * @Assert\Valid()
     */
    protected $parameters;

    /** @var Collection<int, Pricedetail>
     * @ORM\OneToMany(targetEntity="App\Entity\PriceInformations\Pricedetail", mappedBy="currency")
     */
    protected $pricedetails;

    public function __construct()
    {
        $this->pricedetails = new ArrayCollection();
        parent::__construct();
    }

    public function getPricedetails(): Collection
    {
        return $this->pricedetails;
    }

    /**
     * Returns the 3 letter ISO code of this currency.
     *
     * @return string
     */
    public function getIsoCode(): ?string
    {
        return $this->iso_code;
    }

    /**
     * @param  string|null  $iso_code
     *
     * @return Currency
     */
    public function setIsoCode(?string $iso_code): self
    {
        $this->iso_code = $iso_code;

        return $this;
    }

    /**
     * Returns the inverse exchange rate (how many of the current currency the base unit is worth).
     */
    public function getInverseExchangeRate(): ?BigDecimal
    {
        $tmp = $this->getExchangeRate();

        if (null === $tmp || $tmp->isZero()) {
            return null;
        }

        return BigDecimal::one()->dividedBy($tmp, $tmp->getScale(), RoundingMode::HALF_UP);
    }

    /**
     * Returns The exchange rate between this currency and the base currency
     * (how many base units the current currency is worth).
     */
    public function getExchangeRate(): ?BigDecimal
    {
        return $this->exchange_rate;
    }

    /**
     * Sets the exchange rate of the currency.
     *
     * @param BigDecimal|null $exchange_rate The new exchange rate of the currency.
     *                                       Set to null, if the exchange rate is unknown.
     *
     * @return Currency
     */
    public function setExchangeRate(?BigDecimal $exchange_rate): self
    {
        if (null === $exchange_rate) {
            $this->exchange_rate = null;
        }
        $tmp = $exchange_rate->toScale(self::PRICE_SCALE, RoundingMode::HALF_UP);
        //Only change the object, if the value changes, so that doctrine does not detect it as changed.
        if ((string) $tmp !== (string) $this->exchange_rate) {
            $this->exchange_rate = $exchange_rate;
        }

        return $this;
    }
}
