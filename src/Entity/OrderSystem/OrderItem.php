<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace App\Entity\OrderSystem;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
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
use App\Entity\Base\AbstractDBElement;
use App\Entity\Base\TimestampTrait;
use App\Entity\Contracts\TimeStampableInterface;
use App\Entity\Parts\Part;
use App\Entity\Parts\Supplier;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Represents one line item in a purchase order.
 */
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'order_items')]
#[ApiResource(
    operations: [
        new Get(uriTemplate: '/order_items/{id}.{_format}', security: 'is_granted("read", object)'),
        new GetCollection(uriTemplate: '/order_items.{_format}', security: 'is_granted("@orders.read")'),
        new Post(uriTemplate: '/order_items.{_format}', securityPostDenormalize: 'is_granted("create", object)'),
        new Patch(uriTemplate: '/order_items/{id}.{_format}', security: 'is_granted("edit", object)'),
        new Delete(uriTemplate: '/order_items/{id}.{_format}', security: 'is_granted("delete", object)'),
    ],
    normalizationContext: ['groups' => ['order_item:read', 'api:basic:read'], 'openapi_definition_name' => 'Read'],
    denormalizationContext: ['groups' => ['order_item:write', 'api:basic:write'], 'openapi_definition_name' => 'Write'],
)]
#[ApiResource(
    uriTemplate: '/orders/{id}/items.{_format}',
    operations: [
        new GetCollection(
            openapi: new Operation(summary: 'Retrieves the items of a purchase order.'),
            security: 'is_granted("@orders.read")'
        )
    ],
    uriVariables: [
        'id' => new Link(toProperty: 'order', fromClass: Order::class)
    ],
    normalizationContext: ['groups' => ['order_item:read', 'api:basic:read'], 'openapi_definition_name' => 'Read']
)]
#[ApiFilter(PropertyFilter::class)]
#[ApiFilter(LikeFilter::class, properties: ['name', 'supplierPartNr'])]
#[ApiFilter(OrderFilter::class, properties: ['id', 'name', 'quantity'])]
class OrderItem extends AbstractDBElement implements TimeStampableInterface
{
    use TimestampTrait;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'order_id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['order_item:read', 'order_item:write'])]
    protected ?Order $order = null;

    /**
     * The linked Part from the parts catalog. May be null for ad-hoc items not in the catalog.
     */
    #[ORM\ManyToOne(targetEntity: Part::class)]
    #[ORM\JoinColumn(name: 'part_id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['order_item:read', 'order_item:write'])]
    protected ?Part $part = null;

    /**
     * Display name for this item. Auto-populated from part name when a Part is linked.
     */
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['order_item:read', 'order_item:write'])]
    #[ORM\Column(type: Types::STRING, length: 255)]
    protected string $name = '';

    #[Assert\Positive]
    #[Groups(['order_item:read', 'order_item:write'])]
    #[ORM\Column(type: Types::FLOAT)]
    protected float $quantity = 1.0;

    /**
     * The supplier from which this item should be ordered.
     */
    #[ORM\ManyToOne(targetEntity: Supplier::class)]
    #[ORM\JoinColumn(name: 'supplier_id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['order_item:read', 'order_item:write'])]
    protected ?Supplier $supplier = null;

    /**
     * The supplier part number / SKU. Overrides the value from the Part's Orderdetail when set.
     */
    #[Assert\Length(max: 255)]
    #[Groups(['order_item:read', 'order_item:write'])]
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    protected ?string $supplierPartNr = null;

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): self
    {
        $this->order = $order;
        return $this;
    }

    public function getPart(): ?Part
    {
        return $this->part;
    }

    public function setPart(?Part $part): self
    {
        $this->part = $part;
        // Auto-fill name from part if name is empty
        if ($part !== null && $this->name === '') {
            $this->name = $part->getName();
        }
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function setQuantity(float $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getSupplier(): ?Supplier
    {
        return $this->supplier;
    }

    public function setSupplier(?Supplier $supplier): self
    {
        $this->supplier = $supplier;
        return $this;
    }

    public function getSupplierPartNr(): ?string
    {
        return $this->supplierPartNr;
    }

    public function setSupplierPartNr(?string $supplierPartNr): self
    {
        $this->supplierPartNr = $supplierPartNr;
        return $this;
    }

    /**
     * Returns the effective supplier part number: the explicitly set one, or the one from the Part's Orderdetail.
     */
    public function getEffectiveSupplierPartNr(): ?string
    {
        if ($this->supplierPartNr !== null && $this->supplierPartNr !== '') {
            return $this->supplierPartNr;
        }

        if ($this->part !== null) {
            foreach ($this->part->getOrderdetails() as $orderdetail) {
                if ($this->supplier !== null && $orderdetail->getSupplier() === $this->supplier) {
                    return $orderdetail->getSupplierPartNr();
                }
            }
            // Fall back to first available SKU
            if (!$this->part->getOrderdetails()->isEmpty()) {
                return $this->part->getOrderdetails()->first()->getSupplierPartNr();
            }
        }

        return null;
    }
}
