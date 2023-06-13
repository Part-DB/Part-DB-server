<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Entity\PriceInformations;

use Doctrine\DBAL\Types\Types;
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
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity describes a currency that can be used for price information.
 *
 * @extends AbstractStructuralDBElement<CurrencyAttachment, CurrencyParameter>
 */
#[UniqueEntity('iso_code')]
#[ORM\Entity]
#[ORM\Table(name: 'currencies')]
#[ORM\Index(name: 'currency_idx_name', columns: ['name'])]
#[ORM\Index(name: 'currency_idx_parent_name', columns: ['parent_id', 'name'])]
class Currency extends AbstractStructuralDBElement
{
    final public const PRICE_SCALE = 5;

    /**
     * @var BigDecimal|null The exchange rate between this currency and the base currency
     *                      (how many base units the current currency is worth)
     */
    #[ORM\Column(type: 'big_decimal', precision: 11, scale: 5, nullable: true)]
    #[BigDecimalPositive()]
    protected ?BigDecimal $exchange_rate = null;

    /**
     * @var string the 3-letter ISO code of the currency
     */
    #[Assert\Currency]
    #[Groups(['extended', 'full', 'import'])]
    #[ORM\Column(type: Types::STRING)]
    protected string $iso_code = "";

    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent', cascade: ['persist'])]
    #[ORM\OrderBy(['name' => 'ASC'])]
    protected Collection $children;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id')]
    protected ?AbstractStructuralDBElement $parent = null;

    /**
     * @var Collection<int, CurrencyAttachment>
     */
    #[Assert\Valid]
    #[ORM\OneToMany(targetEntity: CurrencyAttachment::class, mappedBy: 'element', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['name' => 'ASC'])]
    protected Collection $attachments;

    /** @var Collection<int, CurrencyParameter>
     */
    #[Assert\Valid]
    #[ORM\OneToMany(targetEntity: CurrencyParameter::class, mappedBy: 'element', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['group' => 'ASC', 'name' => 'ASC'])]
    protected Collection $parameters;

    /** @var Collection<int, Pricedetail>
     */
    #[ORM\OneToMany(targetEntity: Pricedetail::class, mappedBy: 'currency')]
    protected Collection $pricedetails;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->attachments = new ArrayCollection();
        $this->parameters = new ArrayCollection();
        $this->pricedetails = new ArrayCollection();
        parent::__construct();
    }

    public function getPricedetails(): Collection
    {
        return $this->pricedetails;
    }

    /**
     * Returns the 3-letter ISO code of this currency.
     *
     * @return string
     */
    public function getIsoCode(): ?string
    {
        return $this->iso_code;
    }

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

        if (!$tmp instanceof BigDecimal || $tmp->isZero()) {
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
     */
    public function setExchangeRate(?BigDecimal $exchange_rate): self
    {
        if (!$exchange_rate instanceof BigDecimal) {
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
