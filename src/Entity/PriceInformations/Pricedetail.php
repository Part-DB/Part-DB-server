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

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use Doctrine\DBAL\Types\Types;
use App\Entity\Base\AbstractDBElement;
use App\Entity\Base\TimestampTrait;
use App\Entity\Contracts\TimeStampableInterface;
use App\Validator\Constraints\BigDecimal\BigDecimalPositive;
use App\Validator\Constraints\Selectable;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Pricedetail.
 */
#[UniqueEntity(fields: ['min_discount_quantity', 'orderdetail'])]
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table('`pricedetails`')]
#[ORM\Index(columns: ['min_discount_quantity'], name: 'pricedetails_idx_min_discount')]
#[ORM\Index(columns: ['min_discount_quantity', 'price_related_quantity'], name: 'pricedetails_idx_min_discount_price_qty')]
#[ApiResource(
    operations: [
        new Get(security: 'is_granted("read", object)'),
        new GetCollection(security: 'is_granted("@parts.read")'),
        new Post(securityPostDenormalize: 'is_granted("create", object)'),
        new Patch(security: 'is_granted("edit", object)'),
        new Delete(security: 'is_granted("delete", object)'),
    ],
    normalizationContext: ['groups' => ['pricedetail:read', 'pricedetail:read:standalone',  'api:basic:read'], 'openapi_definition_name' => 'Read'],
    denormalizationContext: ['groups' => ['pricedetail:write', 'api:basic:write'], 'openapi_definition_name' => 'Write'],
)]
#[ApiFilter(PropertyFilter::class)]
class Pricedetail extends AbstractDBElement implements TimeStampableInterface
{
    use TimestampTrait;

    final public const PRICE_PRECISION = 5;

    /**
     * @var BigDecimal The price related to the detail. (Given in the selected currency)
     */
    #[Groups(['extended', 'full', 'import', 'pricedetail:read', 'pricedetail:write'])]
    #[ORM\Column(type: 'big_decimal', precision: 11, scale: 5)]
    #[BigDecimalPositive]
    protected BigDecimal $price;

    /**
     * @var ?Currency The currency used for the current price information.
     *                If this is null, the global base unit is assumed
     */
    #[Groups(['extended', 'full', 'import', 'pricedetail:read', 'pricedetail:write'])]
    #[ORM\ManyToOne(targetEntity: Currency::class, inversedBy: 'pricedetails')]
    #[ORM\JoinColumn(name: 'id_currency')]
    #[Selectable]
    protected ?Currency $currency = null;

    /**
     * @var float The amount/quantity for which the price is for (in part unit)
     */
    #[Assert\Positive]
    #[Groups(['extended', 'full', 'import', 'pricedetail:read', 'pricedetail:write'])]
    #[ORM\Column(type: Types::FLOAT)]
    protected float $price_related_quantity = 1.0;

    /**
     * @var float The minimum amount/quantity, which is needed to get this discount (in part unit)
     */
    #[Assert\Positive]
    #[Groups(['extended', 'full', 'import', 'pricedetail:read', 'pricedetail:write'])]
    #[ORM\Column(type: Types::FLOAT)]
    protected float $min_discount_quantity = 1.0;

    /**
     * @var bool
     */
    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $manual_input = true;

    /**
     * @var Orderdetail|null
     */
    #[Assert\NotNull]
    #[ORM\ManyToOne(targetEntity: Orderdetail::class, inversedBy: 'pricedetails')]
    #[ORM\JoinColumn(name: 'orderdetails_id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['pricedetail:read:standalone', 'pricedetail:write'])]
    protected ?Orderdetail $orderdetail = null;

    public function __construct()
    {
        $this->price = BigDecimal::zero()->toScale(self::PRICE_PRECISION);
    }

    public function __clone()
    {
        if ($this->id) {
            $this->addedDate = null;
        }
        parent::__clone();
    }

    /**
     * Helper for updating the timestamp. It is automatically called by doctrine before persisting.
     */
    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        $this->lastModified = new DateTime('now');
        if (!$this->addedDate instanceof \DateTimeInterface) {
            $this->addedDate = new DateTime('now');
        }

        if ($this->orderdetail instanceof Orderdetail) {
            $this->orderdetail->updateTimestamps();
        }
    }

    /********************************************************************************
     *
     *   Getters
     *
     *********************************************************************************/

    /**
     *  Get the orderdetail to which this pricedetail belongs to this pricedetails.
     *
     * @return Orderdetail|null the orderdetail this price belongs to
     */
    public function getOrderdetail(): ?Orderdetail
    {
        return $this->orderdetail;
    }

    /**
     * Returns the price associated with this pricedetail.
     * It is given in current currency and for the price related quantity.
     *
     * @return BigDecimal the price as BigDecimal object, like returned raw from DB
     */
    public function getPrice(): BigDecimal
    {
        return $this->price;
    }

    /**
     *  Get the price for a single unit in the currency associated with this price detail.
     *
     *  @param float|string|BigDecimal $multiplier The returned price (float or string) will be multiplied
     *                                  with this multiplier.
     *
     *      You will get the price for $multiplier parts. If you want the price which is stored
     *           in the database, you have to pass the "price_related_quantity" count as $multiplier.
     *
     * @return BigDecimal the price as a bcmath string
     */
    #[Groups(['pricedetail:read'])]
    #[SerializedName('price_per_unit')]
    public function getPricePerUnit(float|string|BigDecimal $multiplier = 1.0): BigDecimal
    {
        $tmp = BigDecimal::of($multiplier);
        $tmp = $tmp->multipliedBy($this->price);

        return $tmp->dividedBy($this->price_related_quantity, static::PRICE_PRECISION, RoundingMode::HALF_UP);
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
        if ($this->orderdetail && $this->orderdetail->getPart() && !$this->orderdetail->getPart()->useFloatAmount()) {
            $tmp = round($this->price_related_quantity);

            return max($tmp, 1);
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
        if ($this->orderdetail && $this->orderdetail->getPart() && !$this->orderdetail->getPart()->useFloatAmount()) {
            $tmp = round($this->min_discount_quantity);

            return max($tmp, 1);
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

    /**
     * Returns the ISO code of the currency associated with this price information, or null if no currency is selected.
     * Then the global base currency should be assumed.
     * @return string|null
     */
    #[Groups(['pricedetail:read'])]
    #[SerializedName('currency_iso_code')]
    public function getCurrencyISOCode(): ?string
    {
        return $this->currency?->getIsoCode();
    }

    /********************************************************************************
     *
     *   Setters
     *
     *********************************************************************************/

    /**
     * Sets the orderdetail to which this pricedetail belongs to.
     *
     * @return $this
     */
    public function setOrderdetail(Orderdetail $orderdetail): self
    {
        $this->orderdetail = $orderdetail;

        return $this;
    }

    /**
     * Sets the currency associated with the price information.
     * Set to null, to use the global base currency.
     */
    public function setCurrency(?Currency $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     *  Set the price.
     *
     * @param BigDecimal $new_price the new price as a float number
     *
     * This is the price for "price_related_quantity" parts!!
     * Example: if "price_related_quantity" is 10,
     * you have to set here the price for 10 parts!
     *
     * @return $this
     */
    public function setPrice(BigDecimal $new_price): self
    {
        $tmp = $new_price->toScale(self::PRICE_PRECISION, RoundingMode::HALF_UP);
        //Only change the object, if the value changes, so that doctrine does not detect it as changed.
        if ((string) $tmp !== (string) $this->price) {
            $this->price = $tmp;
        }

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
     *
     * @return $this
     */
    public function setMinDiscountQuantity(float $new_min_discount_quantity): self
    {
        $this->min_discount_quantity = $new_min_discount_quantity;

        return $this;
    }
}
