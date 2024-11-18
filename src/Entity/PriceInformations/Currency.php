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

use Doctrine\Common\Collections\Criteria;
use ApiPlatform\Doctrine\Common\Filter\DateFilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use App\ApiPlatform\Filter\LikeFilter;
use App\Entity\Attachments\Attachment;
use App\Repository\CurrencyRepository;
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
#[ORM\Entity(repositoryClass: CurrencyRepository::class)]
#[ORM\Table(name: 'currencies')]
#[ORM\Index(columns: ['name'], name: 'currency_idx_name')]
#[ORM\Index(columns: ['parent_id', 'name'], name: 'currency_idx_parent_name')]
#[ApiResource(
    operations: [
        new Get(security: 'is_granted("read", object)'),
        new GetCollection(security: 'is_granted("@currencies.read")'),
        new Post(securityPostDenormalize: 'is_granted("create", object)'),
        new Patch(security: 'is_granted("edit", object)'),
        new Delete(security: 'is_granted("delete", object)'),
    ],
    normalizationContext: ['groups' => ['currency:read', 'api:basic:read'], 'openapi_definition_name' => 'Read'],
    denormalizationContext: ['groups' => ['currency:write', 'api:basic:write', 'attachment:write', 'parameter:write'], 'openapi_definition_name' => 'Write'],
)]
#[ApiResource(
    uriTemplate: '/currencies/{id}/children.{_format}',
    operations: [
        new GetCollection(
            openapi: new Operation(summary: 'Retrieves the children elements of a currency.'),
            security: 'is_granted("@currencies.read")'
        )
    ],
    uriVariables: [
        'id' => new Link(fromProperty: 'children', fromClass: Currency::class)
    ],
    normalizationContext: ['groups' => ['currency:read', 'api:basic:read'], 'openapi_definition_name' => 'Read']
)]
#[ApiFilter(PropertyFilter::class)]
#[ApiFilter(LikeFilter::class, properties: ["name", "comment", "iso_code"])]
#[ApiFilter(DateFilter::class, strategy: DateFilterInterface::EXCLUDE_NULL)]
#[ApiFilter(OrderFilter::class, properties: ['name', 'id', 'addedDate', 'lastModified'])]
class Currency extends AbstractStructuralDBElement
{
    final public const PRICE_SCALE = 5;

    /**
     * @var BigDecimal|null The exchange rate between this currency and the base currency
     *                      (how many base units the current currency is worth)
     */
    #[ORM\Column(type: 'big_decimal', precision: 11, scale: 5, nullable: true)]
    #[BigDecimalPositive]
    #[Groups(['currency:read', 'currency:write', 'simple', 'extended', 'full', 'import'])]
    #[ApiProperty(readableLink: false, writableLink: false)]
    protected ?BigDecimal $exchange_rate = null;

    #[Groups(['currency:read', 'currency:write'])]
    protected string $comment = "";

    /**
     * @var string the 3-letter ISO code of the currency
     */
    #[Assert\Currency]
    #[Assert\NotBlank]
    #[Groups(['simple', 'extended', 'full', 'import', 'currency:read', 'currency:write'])]
    #[ORM\Column(type: Types::STRING)]
    protected string $iso_code = "";

    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class, cascade: ['persist'])]
    #[ORM\OrderBy(['name' => Criteria::ASC])]
    protected Collection $children;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id')]
    #[Groups(['currency:read', 'currency:write'])]
    #[ApiProperty(readableLink: false, writableLink: false)]
    protected ?AbstractStructuralDBElement $parent = null;

    /**
     * @var Collection<int, CurrencyAttachment>
     */
    #[Assert\Valid]
    #[ORM\OneToMany(mappedBy: 'element', targetEntity: CurrencyAttachment::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['name' => Criteria::ASC])]
    #[Groups(['currency:read', 'currency:write'])]
    protected Collection $attachments;

    #[ORM\ManyToOne(targetEntity: CurrencyAttachment::class)]
    #[ORM\JoinColumn(name: 'id_preview_attachment', onDelete: 'SET NULL')]
    #[Groups(['currency:read', 'currency:write'])]
    protected ?Attachment $master_picture_attachment = null;

    /** @var Collection<int, CurrencyParameter>
     */
    #[Assert\Valid]
    #[ORM\OneToMany(mappedBy: 'element', targetEntity: CurrencyParameter::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['group' => Criteria::ASC, 'name' => 'ASC'])]
    #[Groups(['currency:read', 'currency:write'])]
    protected Collection $parameters;

    /** @var Collection<int, Pricedetail>
     */
    #[ORM\OneToMany(mappedBy: 'currency', targetEntity: Pricedetail::class)]
    protected Collection $pricedetails;

    #[Groups(['currency:read'])]
    protected ?\DateTimeImmutable $addedDate = null;
    #[Groups(['currency:read'])]
    protected ?\DateTimeImmutable $lastModified = null;


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
    public function getIsoCode(): string
    {
        return $this->iso_code;
    }

    public function setIsoCode(string $iso_code): self
    {
        $this->iso_code = $iso_code;

        return $this;
    }

    /**
     * Returns the inverse exchange rate (how many of the current currency the base unit is worth).
     */
    #[Groups(['currency:read'])]
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
        //If the exchange rate is null, set it to null and return.
        if ($exchange_rate === null) {
            $this->exchange_rate = null;
            return $this;
        }

        //Only change the object, if the value changes, so that doctrine does not detect it as changed.
        //Or if the current exchange rate is currently null, as we can not compare it then
        if ($this->exchange_rate === null || $exchange_rate->compareTo($this->exchange_rate) !== 0) {
            $this->exchange_rate = $exchange_rate;
        }

        return $this;
    }
}
