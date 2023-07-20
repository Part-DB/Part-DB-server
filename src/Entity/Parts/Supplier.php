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

namespace App\Entity\Parts;

use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\AttachmentTypeAttachment;
use App\Repository\Parts\SupplierRepository;
use App\Entity\PriceInformations\Orderdetail;
use Doctrine\Common\Collections\ArrayCollection;
use App\Entity\Attachments\SupplierAttachment;
use App\Entity\Base\AbstractCompany;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Parameters\SupplierParameter;
use App\Entity\PriceInformations\Currency;
use App\Validator\Constraints\BigDecimal\BigDecimalPositiveOrZero;
use App\Validator\Constraints\Selectable;
use Brick\Math\BigDecimal;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity represents a supplier of parts (the company that sells the parts).
 *
 * @extends AbstractCompany<SupplierAttachment, SupplierParameter>
 */
#[ORM\Entity(repositoryClass: SupplierRepository::class)]
#[ORM\Table('`suppliers`')]
#[ORM\Index(name: 'supplier_idx_name', columns: ['name'])]
#[ORM\Index(name: 'supplier_idx_parent_name', columns: ['parent_id', 'name'])]
class Supplier extends AbstractCompany
{
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    protected Collection $children;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id')]
    protected ?AbstractStructuralDBElement $parent = null;

    /**
     * @var Collection<int, Orderdetail>|Orderdetail[]
     */
    /**
     * @var Collection<int, Orderdetail>|Orderdetail[]
     */
    #[ORM\OneToMany(targetEntity: Orderdetail::class, mappedBy: 'supplier')]
    protected Collection $orderdetails;

    /**
     * @var Currency|null The currency that should be used by default for order informations with this supplier.
     *                    Set to null, to use global base currency.
     */
    #[ORM\ManyToOne(targetEntity: Currency::class)]
    #[ORM\JoinColumn(name: 'default_currency_id')]
    #[Selectable()]
    protected ?Currency $default_currency = null;

    /**
     * @var BigDecimal|null the shipping costs that have to be paid, when ordering via this supplier
     */
    #[Groups(['extended', 'full', 'import'])]
    #[ORM\Column(name: 'shipping_costs', nullable: true, type: 'big_decimal', precision: 11, scale: 5)]
    #[BigDecimalPositiveOrZero()]
    protected ?BigDecimal $shipping_costs = null;

    /**
     * @var Collection<int, SupplierAttachment>
     */
    #[Assert\Valid]
    #[ORM\OneToMany(targetEntity: SupplierAttachment::class, mappedBy: 'element', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['name' => 'ASC'])]
    protected Collection $attachments;

    #[ORM\ManyToOne(targetEntity: SupplierAttachment::class)]
    #[ORM\JoinColumn(name: 'id_preview_attachment', onDelete: 'SET NULL')]
    protected ?Attachment $master_picture_attachment = null;

    /** @var Collection<int, SupplierParameter>
     */
    #[Assert\Valid]
    #[ORM\OneToMany(targetEntity: SupplierParameter::class, mappedBy: 'element', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['group' => 'ASC', 'name' => 'ASC'])]
    protected Collection $parameters;

    /**
     * Gets the currency that should be used by default, when creating a orderdetail with this supplier.
     */
    public function getDefaultCurrency(): ?Currency
    {
        return $this->default_currency;
    }

    /**
     * Sets the default currency.
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
     * @param BigDecimal|null $shipping_costs a BigDecimal with the shipping costs
     */
    public function setShippingCosts(?BigDecimal $shipping_costs): self
    {
        if (!$shipping_costs instanceof BigDecimal) {
            $this->shipping_costs = null;
        }

        //Only change the object, if the value changes, so that doctrine does not detect it as changed.
        if ((string) $shipping_costs !== (string) $this->shipping_costs) {
            $this->shipping_costs = $shipping_costs;
        }

        return $this;
    }
    public function __construct()
    {
        parent::__construct();
        $this->children = new ArrayCollection();
        $this->orderdetails = new ArrayCollection();
        $this->attachments = new ArrayCollection();
        $this->parameters = new ArrayCollection();
    }
}
