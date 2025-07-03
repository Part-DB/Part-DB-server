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

namespace App\Entity\AssemblySystem;

use App\Repository\AssemblyRepository;
use Doctrine\Common\Collections\Criteria;
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
use App\Validator\Constraints\UniqueObjectCollection;
use App\Validator\Constraints\AssemblySystem\UniqueReferencedAssembly;
use Doctrine\DBAL\Types\Types;
use App\Entity\Attachments\AssemblyAttachment;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Parameters\AssemblyParameter;
use App\Entity\Parts\Part;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * This class represents a assembly in the database.
 *
 * @extends AbstractStructuralDBElement<AssemblyAttachment, AssemblyParameter>
 */
#[ORM\Entity(repositoryClass: AssemblyRepository::class)]
#[ORM\Table(name: 'assemblies')]
#[UniqueEntity(fields: ['ipn'], message: 'assembly.ipn.must_be_unique')]
#[ORM\Index(columns: ['ipn'], name: 'assembly_idx_ipn')]
#[ApiResource(
    operations: [
        new Get(security: 'is_granted("read", object)'),
        new GetCollection(security: 'is_granted("@assemblies.read")'),
        new Post(securityPostDenormalize: 'is_granted("create", object)'),
        new Patch(security: 'is_granted("edit", object)'),
        new Delete(security: 'is_granted("delete", object)'),
    ],
    normalizationContext: ['groups' => ['assembly:read', 'api:basic:read'], 'openapi_definition_name' => 'Read'],
    denormalizationContext: ['groups' => ['assembly:write', 'api:basic:write', 'attachment:write', 'parameter:write'], 'openapi_definition_name' => 'Write'],
)]
#[ApiResource(
    uriTemplate: '/assemblies/{id}/children.{_format}',
    operations: [
        new GetCollection(
            openapi: new Operation(summary: 'Retrieves the children elements of a assembly.'),
            security: 'is_granted("@assemblies.read")'
        )
    ],
    uriVariables: [
        'id' => new Link(fromProperty: 'children', fromClass: Assembly::class)
    ],
    normalizationContext: ['groups' => ['assembly:read', 'api:basic:read'], 'openapi_definition_name' => 'Read']
)]
#[ApiFilter(PropertyFilter::class)]
#[ApiFilter(LikeFilter::class, properties: ["name", "comment", "ipn"])]
#[ApiFilter(OrderFilter::class, properties: ['name', 'id', 'addedDate', 'lastModified'])]
class Assembly extends AbstractStructuralDBElement
{
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
    #[ORM\OrderBy(['name' => Criteria::ASC])]
    protected Collection $children;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id')]
    #[Groups(['assembly:read', 'assembly:write'])]
    #[ApiProperty(readableLink: false, writableLink: false)]
    protected ?AbstractStructuralDBElement $parent = null;

    #[Groups(['assembly:read', 'assembly:write'])]
    protected string $comment = '';

    /**
     * @var Collection<int, AssemblyBOMEntry>
     */
    #[Assert\Valid]
    #[Groups(['extended', 'full', 'import'])]
    #[ORM\OneToMany(targetEntity: AssemblyBOMEntry::class, mappedBy: 'assembly', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[UniqueObjectCollection(message: 'assembly.bom_entry.part_already_in_bom', fields: ['part'])]
    #[UniqueReferencedAssembly]
    #[UniqueObjectCollection(message: 'assembly.bom_entry.project_already_in_bom', fields: ['project'])]
    #[UniqueObjectCollection(message: 'assembly.bom_entry.name_already_in_bom', fields: ['name'])]
    protected Collection $bom_entries;

    #[ORM\Column(type: Types::INTEGER)]
    protected int $order_quantity = 0;

    /**
     * @var string|null The current status of the assembly
     */
    #[Assert\Choice(['draft', 'planning', 'in_production', 'finished', 'archived'])]
    #[Groups(['extended', 'full', 'assembly:read', 'assembly:write', 'import'])]
    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    protected ?string $status = null;

    /**
     * @var string|null The internal ipn number of the assembly
     */
    #[Assert\Length(max: 100)]
    #[Groups(['extended', 'full', 'project:read', 'project:write', 'import'])]
    #[ORM\Column(type: Types::STRING, length: 100, unique: true, nullable: true)]
    #[Length(max: 100)]
    protected ?string $ipn = null;

    /**
     * @var Part|null The (optional) part that represents the builds of this assembly in the stock
     */
    #[ORM\OneToOne(mappedBy: 'built_assembly', targetEntity: Part::class, cascade: ['persist'], orphanRemoval: true)]
    #[Groups(['assembly:read', 'assembly:write'])]
    protected ?Part $build_part = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $order_only_missing_parts = false;

    #[Groups(['simple', 'extended', 'full', 'assembly:read', 'assembly:write'])]
    #[ORM\Column(type: Types::TEXT)]
    protected string $description = '';

    /**
     * @var Collection<int, AssemblyAttachment>
     */
    #[ORM\OneToMany(mappedBy: 'element', targetEntity: AssemblyAttachment::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['name' => Criteria::ASC])]
    #[Groups(['assembly:read', 'assembly:write'])]
    protected Collection $attachments;

    #[ORM\ManyToOne(targetEntity: AssemblyAttachment::class)]
    #[ORM\JoinColumn(name: 'id_preview_attachment', onDelete: 'SET NULL')]
    #[Groups(['assembly:read', 'assembly:write'])]
    protected ?Attachment $master_picture_attachment = null;

    /** @var Collection<int, AssemblyParameter>
     */
    #[ORM\OneToMany(mappedBy: 'element', targetEntity: AssemblyParameter::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['group' => Criteria::ASC, 'name' => 'ASC'])]
    #[Groups(['assembly:read', 'assembly:write'])]
    protected Collection $parameters;

    #[Groups(['assembly:read'])]
    protected ?\DateTimeImmutable $addedDate = null;
    #[Groups(['assembly:read'])]
    protected ?\DateTimeImmutable $lastModified = null;


    /********************************************************************************
     *
     *   Getters
     *
     *********************************************************************************/

    public function __construct()
    {
        $this->attachments = new ArrayCollection();
        $this->parameters = new ArrayCollection();
        parent::__construct();
        $this->bom_entries = new ArrayCollection();
        $this->children = new ArrayCollection();
    }

    public function __clone()
    {
        //When cloning this assembly, we have to clone each bom entry too.
        if ($this->id) {
            $bom_entries = $this->bom_entries;
            $this->bom_entries = new ArrayCollection();
            //Set master attachment is needed
            foreach ($bom_entries as $bom_entry) {
                $clone = clone $bom_entry;
                $this->addBomEntry($clone);
            }
        }

        //Parent has to be last call, as it resets the ID
        parent::__clone();
    }

    /**
     *  Get the order quantity of this assembly.
     *
     * @return int the order quantity
     */
    public function getOrderQuantity(): int
    {
        return $this->order_quantity;
    }

    /**
     *  Get the "order_only_missing_parts" attribute.
     *
     * @return bool the "order_only_missing_parts" attribute
     */
    public function getOrderOnlyMissingParts(): bool
    {
        return $this->order_only_missing_parts;
    }

    /********************************************************************************
     *
     *   Setters
     *
     *********************************************************************************/

    /**
     *  Set the order quantity.
     *
     * @param int $new_order_quantity the new order quantity
     *
     * @return $this
     */
    public function setOrderQuantity(int $new_order_quantity): self
    {
        if ($new_order_quantity < 0) {
            throw new InvalidArgumentException('The new order quantity must not be negative!');
        }
        $this->order_quantity = $new_order_quantity;

        return $this;
    }

    /**
     *  Set the "order_only_missing_parts" attribute.
     *
     * @param bool $new_order_only_missing_parts the new "order_only_missing_parts" attribute
     */
    public function setOrderOnlyMissingParts(bool $new_order_only_missing_parts): self
    {
        $this->order_only_missing_parts = $new_order_only_missing_parts;

        return $this;
    }

    public function getBomEntries(): Collection
    {
        return $this->bom_entries;
    }

    /**
     * @return $this
     */
    public function addBomEntry(AssemblyBOMEntry $entry): self
    {
        $entry->setAssembly($this);
        $this->bom_entries->add($entry);
        return $this;
    }

    /**
     * @return $this
     */
    public function removeBomEntry(AssemblyBOMEntry $entry): self
    {
        $this->bom_entries->removeElement($entry);
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): Assembly
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return string
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * @param  string  $status
     */
    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    /**
     * Returns the internal part number of the assembly.
     * @return string
     */
    public function getIpn(): ?string
    {
        return $this->ipn;
    }

    /**
     * Sets the internal part number of the assembly.
     * @param  string  $ipn The new IPN of the assembly
     */
    public function setIpn(?string $ipn): Assembly
    {
        $this->ipn = $ipn;
        return $this;
    }

    /**
     * Checks if this assembly has an associated part representing the builds of this assembly in the stock.
     */
    public function hasBuildPart(): bool
    {
        return $this->build_part instanceof Part;
    }

    /**
     * Gets the part representing the builds of this assembly in the stock, if it is existing
     */
    public function getBuildPart(): ?Part
    {
        return $this->build_part;
    }

    /**
     * Sets the part representing the builds of this assembly in the stock.
     */
    public function setBuildPart(?Part $build_part): void
    {
        $this->build_part = $build_part;
        if ($build_part instanceof Part) {
            $build_part->setBuiltAssembly($this);
        }
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context, $payload): void
    {
        //If this assembly has subassemblies, and these have builds part, they must be included in the BOM
        foreach ($this->getChildren() as $child) {
            if (!$child->getBuildPart() instanceof Part) {
                continue;
            }
            //We have to search all bom entries for the build part
            $found = false;
            foreach ($this->getBomEntries() as $bom_entry) {
                if ($bom_entry->getPart() === $child->getBuildPart()) {
                    $found = true;
                    break;
                }
            }

            //When the build part is not found, we have to add an error
            if (!$found) {
                $context->buildViolation('assembly.bom_has_to_include_all_subelement_parts')
                    ->atPath('bom_entries')
                    ->setParameter('%assembly_name%', $child->getName())
                    ->setParameter('%part_name%', $child->getBuildPart()->getName())
                    ->addViolation();
            }
        }
    }

    /**
     *  Get all referenced assemblies which uses this assembly.
     *
     * @return Assembly[] all referenced assemblies which uses this assembly as a one-dimensional array of assembly objects
     */
    public function getReferencedAssemblies(): array
    {
        $assemblies = [];

        foreach($this->bom_entries as $entry) {
            if ($entry->getAssembly() !== null) {
                $assemblies[] = $entry->getReferencedAssembly();
            }
        }

        return $assemblies;
    }
}
