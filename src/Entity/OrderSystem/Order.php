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
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use App\ApiPlatform\Filter\LikeFilter;
use App\Entity\Base\AbstractDBElement;
use App\Entity\Base\TimestampTrait;
use App\Entity\Contracts\TimeStampableInterface;
use App\Repository\OrderSystem\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Represents a purchase order — a saved list of parts to order from suppliers.
 */
#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'orders')]
#[ApiResource(
    operations: [
        new Get(security: 'is_granted("read", object)'),
        new GetCollection(security: 'is_granted("@orders.read")'),
        new Post(securityPostDenormalize: 'is_granted("create", object)'),
        new Patch(security: 'is_granted("edit", object)'),
        new Delete(security: 'is_granted("delete", object)'),
    ],
    normalizationContext: ['groups' => ['order:read', 'api:basic:read'], 'openapi_definition_name' => 'Read'],
    denormalizationContext: ['groups' => ['order:write', 'api:basic:write'], 'openapi_definition_name' => 'Write'],
)]
#[ApiFilter(PropertyFilter::class)]
#[ApiFilter(LikeFilter::class, properties: ['name', 'notes'])]
#[ApiFilter(OrderFilter::class, properties: ['name', 'id', 'addedDate', 'lastModified'])]
class Order extends AbstractDBElement implements TimeStampableInterface
{
    use TimestampTrait;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['order:read', 'order:write'])]
    #[ORM\Column(type: Types::STRING, length: 255)]
    protected string $name = '';

    #[Groups(['order:read', 'order:write'])]
    #[ORM\Column(type: Types::TEXT)]
    protected string $notes = '';

    /**
     * @var Collection<int, OrderItem>
     */
    #[Assert\Valid]
    #[Groups(['order:read', 'order:write'])]
    #[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    protected Collection $items;

    /**
     * @var Collection<int, OrderSupplierReference>
     */
    #[Assert\Valid]
    #[Groups(['order:read', 'order:write'])]
    #[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderSupplierReference::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    protected Collection $supplierReferences;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->supplierReferences = new ArrayCollection();
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

    public function getNotes(): string
    {
        return $this->notes;
    }

    public function setNotes(string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(OrderItem $item): self
    {
        $item->setOrder($this);
        $this->items->add($item);
        return $this;
    }

    public function removeItem(OrderItem $item): self
    {
        $this->items->removeElement($item);
        return $this;
    }

    /**
     * @return Collection<int, OrderSupplierReference>
     */
    public function getSupplierReferences(): Collection
    {
        return $this->supplierReferences;
    }

    public function addSupplierReference(OrderSupplierReference $reference): self
    {
        $reference->setOrder($this);
        $this->supplierReferences->add($reference);
        return $this;
    }

    public function removeSupplierReference(OrderSupplierReference $reference): self
    {
        $this->supplierReferences->removeElement($reference);
        return $this;
    }
}
