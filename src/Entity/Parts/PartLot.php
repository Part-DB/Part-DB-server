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

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use App\ApiPlatform\Filter\LikeFilter;
use App\Repository\PartLotRepository;
use Doctrine\DBAL\Types\Types;
use App\Entity\Base\AbstractDBElement;
use App\Entity\Base\TimestampTrait;
use App\Entity\Contracts\NamedElementInterface;
use App\Entity\Contracts\TimeStampableInterface;
use App\Entity\UserSystem\User;
use App\Validator\Constraints\Selectable;
use App\Validator\Constraints\ValidPartLot;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * This entity describes a lot where parts can be stored.
 * It is the connection between a part and its store locations.
 *
 * @see \App\Tests\Entity\Parts\PartLotTest
 */
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'part_lots')]
#[ORM\Index(columns: ['instock_unknown', 'expiration_date', 'id_part'], name: 'part_lots_idx_instock_un_expiration_id_part')]
#[ORM\Index(columns: ['needs_refill'], name: 'part_lots_idx_needs_refill')]
#[ORM\Index(columns: ['vendor_barcode'], name: 'part_lots_idx_barcode')]
#[ValidPartLot]
#[ApiResource(
    operations: [
        new Get(security: 'is_granted("read", object)'),
        new GetCollection(security: 'is_granted("@parts.read")'),
        new Post(securityPostDenormalize: 'is_granted("create", object)'),
        new Patch(security: 'is_granted("edit", object)'),
        new Delete(security: 'is_granted("delete", object)'),
    ],
    normalizationContext: ['groups' => ['part_lot:read', 'part_lot:read:standalone',  'api:basic:read', 'pricedetail:read'], 'openapi_definition_name' => 'Read'],
    denormalizationContext: ['groups' => ['part_lot:write', 'api:basic:write'], 'openapi_definition_name' => 'Write'],
)]
#[ApiFilter(PropertyFilter::class)]
#[ApiFilter(LikeFilter::class, properties: ["description", "comment"])]
#[ApiFilter(DateFilter::class, strategy: DateFilter::EXCLUDE_NULL)]
#[ApiFilter(BooleanFilter::class, properties: ['instock_unknown', 'needs_refill'])]
#[ApiFilter(RangeFilter::class, properties: ['amount'])]
#[ApiFilter(OrderFilter::class, properties: ['description', 'comment', 'addedDate', 'lastModified'])]
class PartLot extends AbstractDBElement implements TimeStampableInterface, NamedElementInterface
{
    use TimestampTrait;

    /**
     * @var string A short description about this lot, shown in table
     */
    #[Groups(['simple', 'extended', 'full', 'import', 'part_lot:read', 'part_lot:write'])]
    #[ORM\Column(type: Types::TEXT)]
    protected string $description = '';

    /**
     * @var string a comment stored with this lot
     */
    #[Groups(['full', 'import', 'part_lot:read', 'part_lot:write'])]
    #[ORM\Column(type: Types::TEXT)]
    protected string $comment = '';

    /**
     * @var \DateTimeInterface|null Set a time until when the lot must be used.
     *                Set to null, if the lot can be used indefinitely.
     */
    #[Groups(['extended', 'full', 'import', 'part_lot:read', 'part_lot:write'])]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, name: 'expiration_date', nullable: true)]
    protected ?\DateTimeInterface $expiration_date = null;

    /**
     * @var StorageLocation|null The storelocation of this lot
     */
    #[Groups(['simple', 'extended', 'full', 'import', 'part_lot:read', 'part_lot:write'])]
    #[ORM\ManyToOne(targetEntity: StorageLocation::class, fetch: 'EAGER')]
    #[ORM\JoinColumn(name: 'id_store_location')]
    #[Selectable()]
    protected ?StorageLocation $storage_location = null;

    /**
     * @var bool If this is set to true, the instock amount is marked as not known
     */
    #[Groups(['simple', 'extended', 'full', 'import', 'part_lot:read', 'part_lot:write'])]
    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $instock_unknown = false;

    /**
     * @var float The amount of parts in this lot. For integer-quantities this value is rounded to the next integer.
     */
    #[Assert\PositiveOrZero]
    #[Groups(['simple', 'extended', 'full', 'import', 'part_lot:read', 'part_lot:write'])]
    #[ORM\Column(type: Types::FLOAT)]
    protected float $amount = 0.0;

    /**
     * @var bool determines if this lot was manually marked for refilling
     */
    #[Groups(['extended', 'full', 'import', 'part_lot:read', 'part_lot:write'])]
    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $needs_refill = false;

    /**
     * @var Part|null The part that is stored in this lot
     */
    #[Assert\NotNull]
    #[ORM\ManyToOne(targetEntity: Part::class, inversedBy: 'partLots')]
    #[ORM\JoinColumn(name: 'id_part', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['part_lot:read:standalone', 'part_lot:write'])]
    protected ?Part $part = null;

    /**
     * @var User|null The owner of this part lot
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_owner', onDelete: 'SET NULL')]
    #[Groups(['part_lot:read', 'part_lot:write'])]
    protected ?User $owner = null;

    /**
     * @var string|null The content of the barcode of this part lot (e.g. a barcode on the package put by the vendor)
     */
    #[ORM\Column(type: Types::STRING, nullable: true)]
    protected ?string $vendor_barcode = null;

    public function __clone()
    {
        if ($this->id) {
            $this->addedDate = null;
        }
        parent::__clone();
    }

    /**
     * Check if the current part lot is expired.
     * This is the case, if the expiration date is greater the current date.
     *
     * @return bool|null True, if the part lot is expired. Returns null, if no expiration date was set.
     *
     * @throws Exception If an error with the DateTime occurs
     */
    public function isExpired(): ?bool
    {
        if (!$this->expiration_date instanceof \DateTimeInterface) {
            return null;
        }

        //Check if the expiration date is bigger then current time
        return $this->expiration_date < new DateTime('now');
    }

    /**
     * Gets the description of the part lot. Similar to a "name" of the part lot.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Sets the description of the part lot.
     */
    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Gets the comment for this part lot.
     */
    public function getComment(): string
    {
        return $this->comment;
    }

    /**
     * Sets the comment for this part lot.
     */
    public function setComment(string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    /**
     * Gets the expiration date for the part lot. Returns null, if no expiration date was set.
     */
    public function getExpirationDate(): ?\DateTimeInterface
    {
        return $this->expiration_date;
    }

    /**
     * Sets the expiration date for the part lot. Set to null, if the part lot does not expire.
     *
     *
     */
    public function setExpirationDate(?\DateTimeInterface $expiration_date): self
    {
        $this->expiration_date = $expiration_date;

        return $this;
    }

    /**
     * Gets the storage location, where this part lot is stored.
     *
     * @return StorageLocation|null The store location where this part is stored
     */
    public function getStorageLocation(): ?StorageLocation
    {
        return $this->storage_location;
    }

    /**
     * Sets the storage location, where this part lot is stored.
     */
    public function setStorageLocation(?StorageLocation $storage_location): self
    {
        $this->storage_location = $storage_location;

        return $this;
    }

    /**
     * Return the part that is stored in this part lot.
     */
    public function getPart(): ?Part
    {
        return $this->part;
    }

    /**
     * Sets the part that is stored in this part lot.
     */
    public function setPart(Part $part): self
    {
        $this->part = $part;

        return $this;
    }

    /**
     * Checks if the instock value in the part lot is unknown.
     */
    public function isInstockUnknown(): bool
    {
        return $this->instock_unknown;
    }

    /**
     * Set the unknown instock status of this part lot.
     */
    public function setInstockUnknown(bool $instock_unknown): self
    {
        $this->instock_unknown = $instock_unknown;

        return $this;
    }

    public function getAmount(): float
    {
        if ($this->part instanceof Part && !$this->part->useFloatAmount()) {
            return round($this->amount);
        }

        return $this->amount;
    }

    /**
     * Sets the amount of parts in the part lot.
     * If null is passed, amount will be set to unknown.
     *
     * @return $this
     */
    public function setAmount(?float $new_amount): self
    {
        //Treat null like unknown amount
        if (null === $new_amount) {
            $this->instock_unknown = true;
            $new_amount = 0.0;
        }

        $this->amount = $new_amount;

        return $this;
    }

    public function isNeedsRefill(): bool
    {
        return $this->needs_refill;
    }

    public function setNeedsRefill(bool $needs_refill): self
    {
        $this->needs_refill = $needs_refill;

        return $this;
    }

    /**
     * Returns the owner of this part lot.
     */
    public function getOwner(): ?User
    {
        return $this->owner;
    }

    /**
     * Sets the owner of this part lot.
     */
    public function setOwner(?User $owner): PartLot
    {
        $this->owner = $owner;
        return $this;
    }

    public function getName(): string
    {
        return $this->description;
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context, $payload): void
    {
        //Ensure that the owner is not the anonymous user
        if ($this->getOwner() && $this->getOwner()->isAnonymousUser()) {
            $context->buildViolation('validator.part_lot.owner_must_not_be_anonymous')
                ->atPath('owner')
                ->addViolation();
        }

        //When the storage location sets the owner must match, the part lot owner must match the storage location owner
        if ($this->getStorageLocation() && $this->getStorageLocation()->isPartOwnerMustMatch()
            && $this->getStorageLocation()->getOwner() && $this->getOwner() && ($this->getOwner() !== $this->getStorageLocation()->getOwner()
                && $this->owner->getID() !== $this->getStorageLocation()->getOwner()->getID())) {
            $context->buildViolation('validator.part_lot.owner_must_match_storage_location_owner')
                ->setParameter('%owner_name%', $this->getStorageLocation()->getOwner()->getFullName(true))
                ->atPath('owner')
                ->addViolation();
        }
    }
}
