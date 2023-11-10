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

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
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
use Doctrine\DBAL\Types\Types;
use App\Entity\Base\AbstractDBElement;
use App\Entity\Base\TimestampTrait;
use App\Entity\Contracts\NamedElementInterface;
use App\Entity\Contracts\TimeStampableInterface;
use App\Entity\Parts\Part;
use App\Entity\Parts\Supplier;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Orderdetail.
 */
#[UniqueEntity(['supplierpartnr', 'supplier', 'part'])]
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table('`orderdetails`')]
#[ORM\Index(name: 'orderdetails_supplier_part_nr', columns: ['supplierpartnr'])]
#[ApiResource(
    operations: [
        new Get(security: 'is_granted("read", object)'),
        new GetCollection(security: 'is_granted("@parts.read")'),
        new Post(securityPostDenormalize: 'is_granted("create", object)'),
        new Patch(security: 'is_granted("edit", object)'),
        new Delete(security: 'is_granted("delete", object)'),
    ],
    normalizationContext: ['groups' => ['orderdetail:read', 'orderdetail:read:standalone',  'api:basic:read', 'pricedetail:read'], 'openapi_definition_name' => 'Read'],
    denormalizationContext: ['groups' => ['orderdetail:write', 'api:basic:write'], 'openapi_definition_name' => 'Write'],
)]
#[ApiResource(
    uriTemplate: '/parts/{id}/orderdetails.{_format}',
    operations: [
        new GetCollection(
            openapi: new Operation(summary: 'Retrieves the orderdetails of a part.'),
            security: 'is_granted("@parts.read")'
        )
    ],
    uriVariables: [
        'id' => new Link(toProperty: 'part', fromClass: Part::class)
    ],
    normalizationContext: ['groups' => ['orderdetail:read', 'pricedetail:read', 'api:basic:read'], 'openapi_definition_name' => 'Read']
)]
#[ApiFilter(PropertyFilter::class)]
#[ApiFilter(PropertyFilter::class)]
#[ApiFilter(LikeFilter::class, properties: ["supplierpartnr", "supplier_product_url"])]
#[ApiFilter(BooleanFilter::class, properties: ["obsolete"])]
#[ApiFilter(DateFilter::class, strategy: DateFilter::EXCLUDE_NULL)]
#[ApiFilter(OrderFilter::class, properties: ['supplierpartnr', 'id', 'addedDate', 'lastModified'])]
class Orderdetail extends AbstractDBElement implements TimeStampableInterface, NamedElementInterface
{
    use TimestampTrait;

    #[Assert\Valid]
    #[Groups(['extended', 'full', 'import', 'orderdetail:read', 'orderdetail:write'])]
    #[ORM\OneToMany(targetEntity: Pricedetail::class, mappedBy: 'orderdetail', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['min_discount_quantity' => 'ASC'])]
    protected Collection $pricedetails;

    /**
     * @var string The order number of the part at the supplier
     */
    #[Groups(['extended', 'full', 'import', 'orderdetail:read', 'orderdetail:write'])]
    #[ORM\Column(type: Types::STRING)]
    protected string $supplierpartnr = '';

    /**
     * @var bool True if this part is obsolete/not available anymore at the supplier
     */
    #[Groups(['extended', 'full', 'import', 'orderdetail:read', 'orderdetail:write'])]
    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $obsolete = false;

    /**
     * @var string The URL to the product on the supplier's website
     */
    #[Assert\Url]
    #[Groups(['full', 'import', 'orderdetail:read', 'orderdetail:write'])]
    #[ORM\Column(type: Types::TEXT)]
    protected string $supplier_product_url = '';

    /**
     * @var Part|null The part with which this orderdetail is associated
     */
    #[Assert\NotNull]
    #[ORM\ManyToOne(targetEntity: Part::class, inversedBy: 'orderdetails')]
    #[Groups(['orderdetail:read:standalone', 'orderdetail:write'])]
    #[ORM\JoinColumn(name: 'part_id', nullable: false, onDelete: 'CASCADE')]
    protected ?Part $part = null;

    /**
     * @var Supplier|null The supplier of this orderdetail
     */
    #[Assert\NotNull(message: 'validator.orderdetail.supplier_must_not_be_null')]
    #[Groups(['extended', 'full', 'import', 'orderdetail:read', 'orderdetail:write'])]
    #[ORM\ManyToOne(targetEntity: Supplier::class, inversedBy: 'orderdetails')]
    #[ORM\JoinColumn(name: 'id_supplier')]
    protected ?Supplier $supplier = null;

    public function __construct()
    {
        $this->pricedetails = new ArrayCollection();
    }

    public function __clone()
    {
        if ($this->id) {
            $this->addedDate = null;
            $pricedetails = $this->pricedetails;
            $this->pricedetails = new ArrayCollection();
            //Set master attachment is needed
            foreach ($pricedetails as $pricedetail) {
                $this->addPricedetail(clone $pricedetail);
            }
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

        if ($this->part instanceof Part) {
            $this->part->updateTimestamps();
        }
    }

    /********************************************************************************
     *
     *   Getters
     *
     *********************************************************************************/

    /**
     * Get the part.
     *
     * @return Part|null the part of this orderdetails
     */
    public function getPart(): ?Part
    {
        return $this->part;
    }

    /**
     * Get the supplier.
     *
     * @return Supplier the supplier of this orderdetails
     */
    public function getSupplier(): ?Supplier
    {
        return $this->supplier;
    }

    /**
     * Get the supplier part-nr.
     *
     * @return string the part-nr
     */
    public function getSupplierPartNr(): string
    {
        return $this->supplierpartnr;
    }

    /**
     * Get if this orderdetails is obsolete.
     *
     * "Orderdetails is obsolete" means that the part with that supplier-part-nr
     * is no longer available from the supplier of that orderdetails.
     *
     * @return bool * true if this part is obsolete at that supplier
     *              * false if this part isn't obsolete at that supplier
     */
    public function getObsolete(): bool
    {
        return $this->obsolete;
    }

    /**
     * Get the link to the website of the article on the supplier's website.
     *
     * @param bool $no_automatic_url Set this to true, if you only want to get the local set product URL for this Orderdetail
     *                               and not an automatic generated one, based from the Supplier
     *
     * @return string the link to the article
     */
    public function getSupplierProductUrl(bool $no_automatic_url = false): string
    {
        if ($no_automatic_url || '' !== $this->supplier_product_url) {
            return $this->supplier_product_url;
        }

        if (!$this->getSupplier() instanceof Supplier) {
            return '';
        }

        return $this->getSupplier()->getAutoProductUrl($this->supplierpartnr); // maybe an automatic url is available...
    }

    /**
     * Get all pricedetails.
     *
     * @return Collection<int, Pricedetail>
     */
    public function getPricedetails(): Collection
    {
        return $this->pricedetails;
    }

    /**
     * Adds a price detail to this orderdetail.
     *
     * @param Pricedetail $pricedetail The pricedetail to add
     */
    public function addPricedetail(Pricedetail $pricedetail): self
    {
        $pricedetail->setOrderdetail($this);
        $this->pricedetails->add($pricedetail);

        return $this;
    }

    /**
     * Removes a price detail from this orderdetail.
     */
    public function removePricedetail(Pricedetail $pricedetail): self
    {
        $this->pricedetails->removeElement($pricedetail);

        return $this;
    }

    /**
     * Find the pricedetail that is correct for the desired amount (the one with the greatest discount value with a
     * minimum order amount of the wished quantity).
     *
     * @param float $quantity this is the quantity to choose the correct pricedetails
     *
     * @return Pricedetail|null the price as a bcmath string. Null if there are no orderdetails for the given quantity
     */
    public function findPriceForQty(float $quantity = 1.0): ?Pricedetail
    {
        if ($quantity <= 0) {
            return null;
        }

        $all_pricedetails = $this->getPricedetails();

        $correct_pricedetail = null;
        foreach ($all_pricedetails as $pricedetail) {
            // choose the correct pricedetails for the chosen quantity ($quantity)
            if ($quantity < $pricedetail->getMinDiscountQuantity()) {
                break;
            }

            $correct_pricedetail = $pricedetail;
        }

        return $correct_pricedetail;
    }

    /********************************************************************************
     *
     *   Setters
     *
     *********************************************************************************/
    /**
     * Sets a new part with which this orderdetail is associated.
     */
    public function setPart(Part $part): self
    {
        $this->part = $part;

        return $this;
    }

    /**
     * Sets the new supplier associated with this orderdetail.
     */
    public function setSupplier(Supplier $new_supplier): self
    {
        $this->supplier = $new_supplier;

        return $this;
    }

    /**
     * Set the supplier part-nr.
     *
     * @param string $new_supplierpartnr the new supplier-part-nr
     */
    public function setSupplierpartnr(string $new_supplierpartnr): self
    {
        $this->supplierpartnr = $new_supplierpartnr;

        return $this;
    }

    /**
     * Set if the part is obsolete at the supplier of that orderdetails.
     *
     * @param bool $new_obsolete true means that this part is obsolete
     */
    public function setObsolete(bool $new_obsolete): self
    {
        $this->obsolete = $new_obsolete;

        return $this;
    }

    /**
     * Sets the custom product supplier URL for this order detail.
     * Set this to "", if the function getSupplierProductURL should return the automatic generated URL.
     *
     * @param string $new_url The new URL for the supplier URL
     */
    public function setSupplierProductUrl(string $new_url): self
    {
        //Only change the internal URL if it is not the auto generated one
        if ($this->supplier && $new_url === $this->supplier->getAutoProductUrl($this->getSupplierPartNr())) {
            return $this;
        }

        $this->supplier_product_url = $new_url;

        return $this;
    }

    public function getName(): string
    {
        return $this->getSupplierPartNr();
    }
}
