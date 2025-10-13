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

use ApiPlatform\Doctrine\Common\Filter\DateFilterInterface;
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
use App\ApiPlatform\Filter\EntityFilter;
use App\ApiPlatform\Filter\LikeFilter;
use App\ApiPlatform\Filter\PartStoragelocationFilter;
use App\ApiPlatform\Filter\TagFilter;
use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\AttachmentContainingDBElement;
use App\Entity\Attachments\PartAttachment;
use App\Entity\EDA\EDAPartInfo;
use App\Entity\InfoProviderSystem\BulkInfoProviderImportJobPart;
use App\Entity\Parameters\ParametersTrait;
use App\Entity\Parameters\PartParameter;
use App\Entity\Parts\PartTraits\AdvancedPropertyTrait;
use App\Entity\Parts\PartTraits\AssociationTrait;
use App\Entity\Parts\PartTraits\BasicPropertyTrait;
use App\Entity\Parts\PartTraits\EDATrait;
use App\Entity\Parts\PartTraits\InstockTrait;
use App\Entity\Parts\PartTraits\ManufacturerTrait;
use App\Entity\Parts\PartTraits\OrderTrait;
use App\Entity\Parts\PartTraits\ProjectTrait;
use App\EntityListeners\TreeCacheInvalidationListener;
use App\Repository\PartRepository;
use App\Validator\Constraints\UniqueObjectCollection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Part class.
 *
 * The class properties are split over various traits in directory PartTraits.
 * Otherwise, this class would be too big, to be maintained.
 * @see \App\Tests\Entity\Parts\PartTest
 * @extends AttachmentContainingDBElement<PartAttachment>
 * @template-use ParametersTrait<PartParameter>
 */
#[ORM\Entity(repositoryClass: PartRepository::class)]
#[ORM\EntityListeners([TreeCacheInvalidationListener::class])]
#[ORM\Table('`parts`')]
#[ORM\Index(columns: ['datetime_added', 'name', 'last_modified', 'id', 'needs_review'], name: 'parts_idx_datet_name_last_id_needs')]
#[ORM\Index(columns: ['name'], name: 'parts_idx_name')]
#[ORM\Index(columns: ['ipn'], name: 'parts_idx_ipn')]
#[ApiResource(
    operations: [
        new Get(normalizationContext: [
            'groups' => [
                'part:read',
                'provider_reference:read',
                'api:basic:read',
                'part_lot:read',
                'orderdetail:read',
                'pricedetail:read',
                'parameter:read',
                'attachment:read',
                'eda_info:read'
            ],
            'openapi_definition_name' => 'Read',
        ], security: 'is_granted("read", object)'),
        new GetCollection(security: 'is_granted("@parts.read")'),
        new Post(securityPostDenormalize: 'is_granted("create", object)'),
        new Patch(security: 'is_granted("edit", object)'),
        new Delete(security: 'is_granted("delete", object)'),
    ],
    normalizationContext: ['groups' => ['part:read', 'provider_reference:read', 'api:basic:read', 'part_lot:read'], 'openapi_definition_name' => 'Read'],
    denormalizationContext: ['groups' => ['part:write', 'api:basic:write', 'eda_info:write', 'attachment:write', 'parameter:write'], 'openapi_definition_name' => 'Write'],
)]
#[ApiFilter(PropertyFilter::class)]
#[ApiFilter(EntityFilter::class, properties: ["category", "footprint", "manufacturer", "partUnit"])]
#[ApiFilter(PartStoragelocationFilter::class, properties: ["storage_location"])]
#[ApiFilter(LikeFilter::class, properties: ["name", "comment", "description", "ipn", "manufacturer_product_number"])]
#[ApiFilter(TagFilter::class, properties: ["tags"])]
#[ApiFilter(BooleanFilter::class, properties: ["favorite", "needs_review"])]
#[ApiFilter(RangeFilter::class, properties: ["mass", "minamount"])]
#[ApiFilter(DateFilter::class, strategy: DateFilterInterface::EXCLUDE_NULL)]
#[ApiFilter(OrderFilter::class, properties: ['name', 'id', 'addedDate', 'lastModified'])]
class Part extends AttachmentContainingDBElement
{
    use AdvancedPropertyTrait;
    //use MasterAttachmentTrait;
    use BasicPropertyTrait;
    use InstockTrait;
    use ManufacturerTrait;
    use OrderTrait;
    use ParametersTrait;
    use ProjectTrait;
    use AssociationTrait;
    use EDATrait;

    /** @var Collection<int, PartParameter>
     */
    #[Assert\Valid]
    #[Groups(['full', 'part:read', 'part:write', 'import'])]
    #[ORM\OneToMany(mappedBy: 'element', targetEntity: PartParameter::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['group' => Criteria::ASC, 'name' => 'ASC'])]
    #[UniqueObjectCollection(fields: ['name', 'group', 'element'])]
    protected Collection $parameters;


    /** *************************************************************
     * Overridden properties
     * (They are defined here and not in a trait, to avoid conflicts).
     ****************************************************************/

    /**
     * @var string The name of this part
     */
    protected string $name = '';

    /**
     * @var Collection<int, PartAttachment>
     */
    #[Assert\Valid]
    #[Groups(['full', 'part:read', 'part:write'])]
    #[ORM\OneToMany(mappedBy: 'element', targetEntity: PartAttachment::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['name' => Criteria::ASC])]
    protected Collection $attachments;

    /**
     * @var Attachment|null
     */
    #[Assert\Expression('value == null or value.isPicture()', message: 'part.master_attachment.must_be_picture')]
    #[ORM\ManyToOne(targetEntity: PartAttachment::class)]
    #[ORM\JoinColumn(name: 'id_preview_attachment', onDelete: 'SET NULL')]
    #[Groups(['part:read', 'part:write'])]
    protected ?Attachment $master_picture_attachment = null;

    #[Groups(['part:read'])]
    protected ?\DateTimeImmutable $addedDate = null;
    #[Groups(['part:read'])]
    protected ?\DateTimeImmutable $lastModified = null;

    /**
     * @var Collection<int, BulkInfoProviderImportJobPart>
     */
    #[ORM\OneToMany(mappedBy: 'part', targetEntity: BulkInfoProviderImportJobPart::class, cascade: ['remove'], orphanRemoval: true)]
    protected Collection $bulkImportJobParts;


    public function __construct()
    {
        $this->attachments = new ArrayCollection();
        parent::__construct();
        $this->partLots = new ArrayCollection();
        $this->orderdetails = new ArrayCollection();
        $this->parameters = new ArrayCollection();
        $this->project_bom_entries = new ArrayCollection();

        $this->associated_parts_as_owner = new ArrayCollection();
        $this->associated_parts_as_other = new ArrayCollection();
        $this->bulkImportJobParts = new ArrayCollection();

        //By default, the part has no provider
        $this->providerReference = InfoProviderReference::noProvider();
        $this->eda_info = new EDAPartInfo();
    }

    public function __clone()
    {
        if ($this->id) {
            //Deep clone part lots
            $lots = $this->partLots;
            $this->partLots = new ArrayCollection();
            foreach ($lots as $lot) {
                $this->addPartLot(clone $lot);
            }

            //Deep clone order details
            $orderdetails = $this->orderdetails;
            $this->orderdetails = new ArrayCollection();
            foreach ($orderdetails as $orderdetail) {
                $this->addOrderdetail(clone $orderdetail);
            }

            //Deep clone parameters
            $parameters = $this->parameters;
            $this->parameters = new ArrayCollection();
            foreach ($parameters as $parameter) {
                $this->addParameter(clone $parameter);
            }

            //Deep clone the owned part associations (the owned ones make not much sense without the owner)
            $ownedAssociations = $this->associated_parts_as_owner;
            $this->associated_parts_as_owner = new ArrayCollection();
            foreach ($ownedAssociations as $association) {
                $this->addAssociatedPartsAsOwner(clone $association);
            }

            //Deep clone info provider
            $this->providerReference = clone $this->providerReference;
            $this->eda_info = clone $this->eda_info;
        }
        parent::__clone();
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context, $payload): void
    {
        //Ensure that the part name fullfills the regex of the category
        if ($this->category instanceof Category) {
            $regex = $this->category->getPartnameRegex();
            if ($regex !== '' && !preg_match($regex, $this->name)) {
                $context->buildViolation('part.name.must_match_category_regex')
                    ->atPath('name')
                    ->setParameter('%regex%', $regex)
                    ->addViolation();
            }
        }
    }

    /**
     * Get all bulk import job parts for this part
     * @return Collection<int, BulkInfoProviderImportJobPart>
     */
    public function getBulkImportJobParts(): Collection
    {
        return $this->bulkImportJobParts;
    }

    /**
     * Add a bulk import job part to this part
     */
    public function addBulkImportJobPart(BulkInfoProviderImportJobPart $jobPart): self
    {
        if (!$this->bulkImportJobParts->contains($jobPart)) {
            $this->bulkImportJobParts->add($jobPart);
            $jobPart->setPart($this);
        }
        return $this;
    }

    /**
     * Remove a bulk import job part from this part
     */
    public function removeBulkImportJobPart(BulkInfoProviderImportJobPart $jobPart): self
    {
        if ($this->bulkImportJobParts->removeElement($jobPart)) {
            if ($jobPart->getPart() === $this) {
                $jobPart->setPart(null);
            }
        }
        return $this;
    }
}
