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
     * @var string|null The exchange rate between this currency and the base currency
     *                  (how many base units the current currency is worth)
     * @ORM\Column(type="decimal", precision=11, scale=5, nullable=true)
     * @Assert\Positive()
     */
    protected $exchange_rate;

    /**
     * @var string the 3 letter ISO code of the currency
     * @ORM\Column(type="string")
     * @Assert\Currency()
     */
    protected $iso_code;

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
     * @var Collection|CurrencyAttachment[]
     * @ORM\OneToMany(targetEntity="App\Entity\Attachments\CurrencyAttachment", mappedBy="element", cascade={"persist", "remove"}, orphanRemoval=true)
     * @Assert\Valid()
     */
    protected $attachments;

    /** @var CurrencyParameter[]
     * @ORM\OneToMany(targetEntity="App\Entity\Parameters\CurrencyParameter", mappedBy="element", cascade={"persist", "remove"}, orphanRemoval=true)
     * @Assert\Valid()
     */
    protected $parameters;

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
     * @param string $iso_code
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
    public function getInverseExchangeRate(): ?string
    {
        $tmp = $this->getExchangeRate();

        if (null === $tmp || '0' === $tmp) {
            return null;
        }

        return bcdiv('1', $tmp, static::PRICE_SCALE);
    }

    /**
     * Returns The exchange rate between this currency and the base currency
     * (how many base units the current currency is worth).
     */
    public function getExchangeRate(): ?string
    {
        return $this->exchange_rate;
    }

    /**
     * Sets the exchange rate of the currency.
     *
     * @param string|null $exchange_rate The new exchange rate of the currency.
     *                                   Set to null, if the exchange rate is unknown.
     *
     * @return Currency
     */
    public function setExchangeRate(?string $exchange_rate): self
    {
        $this->exchange_rate = $exchange_rate;

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
        return 'C'.$this->getID();
    }
}
