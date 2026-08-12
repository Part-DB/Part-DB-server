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

namespace App\Entity\ProjectSystem;

use Doctrine\Common\Collections\Criteria;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\McpToolCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use App\ApiPlatform\Filter\LikeFilter;
use App\Entity\Attachments\Attachment;
use App\Mcp\DTO\ElementByIdInput;
use App\Mcp\DTO\StructuralElementOverview;
use App\Mcp\DTO\StructuralElementSearchInput;
use App\Repository\Parts\DeviceRepository;
use App\State\Mcp\GetStructuralElementDetailsProcessor;
use App\State\Mcp\ListStructuralElementsProcessor;
use App\Validator\Constraints\UniqueObjectCollection;
use Doctrine\DBAL\Types\Types;
use App\Entity\Attachments\ProjectAttachment;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Parameters\ProjectParameter;
use App\Entity\Parts\Part;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * This class represents a project in the database.
 *
 * @extends AbstractStructuralDBElement<ProjectAttachment, ProjectParameter>
 */
#[ORM\Entity(repositoryClass: DeviceRepository::class)]
#[ORM\Table(name: 'projects')]
#[ApiResource(
    operations: [
        new Get(security: 'is_granted("read", object)'),
        new GetCollection(security: 'is_granted("@projects.read")'),
        new Post(securityPostDenormalize: 'is_granted("create", object)'),
        new Patch(security: 'is_granted("edit", object)'),
        new Delete(security: 'is_granted("delete", object)'),
        new GetCollection(
            uriTemplate: '/projects/{id}/children.{_format}',
            uriVariables: ['id' => new Link(fromProperty: 'children', fromClass: Project::class)],
            openapi: new Operation(summary: 'Retrieves the children elements of a project.'),
            security: 'is_granted("@projects.read")'
        ),
    ],
    normalizationContext: ['groups' => ['project:read', 'api:basic:read'], 'openapi_definition_name' => 'Read'],
    denormalizationContext: ['groups' => ['project:write', 'api:basic:write', 'attachment:write', 'parameter:write'], 'openapi_definition_name' => 'Write'],
    mcp: [
        'list_projects' => new McpToolCollection(
            title: 'List/search projects',
            description: 'List all projects, optionally filtered by a keyword matched against the name and comment. Each entry includes its full hierarchical path, and results are sorted by that path so parents are immediately followed by their own children, making it easy to derive the tree structure from the flat list. Use get_project_details for a specific project to retrieve its BOM entries, status and other details.',
            annotations: ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false],
            output: StructuralElementOverview::class,
            normalizationContext: ['groups' => ['mcp_structural_overview:read']],
            input: StructuralElementSearchInput::class,
            security: 'is_granted("@projects.read")',
            processor: ListStructuralElementsProcessor::class,
        ),
        'get_project_details' => new McpTool(
            title: 'Get project details by ID',
            description: 'Get detailed information about a specific project by its database ID, including its BOM entries, status, description and associated build part.',
            annotations: ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false],
            normalizationContext: ['groups' => ['project:read', 'api:basic:read', 'mcp_project_details:read']],
            input: ElementByIdInput::class,
            security: 'is_granted("@projects.read")',
            validate: true,
            processor: GetStructuralElementDetailsProcessor::class,
        ),
    ],
)]
#[ApiFilter(PropertyFilter::class)]
#[ApiFilter(LikeFilter::class, properties: ["name", "comment"])]
#[ApiFilter(OrderFilter::class, properties: ['name', 'id', 'addedDate', 'lastModified'])]
class Project extends AbstractStructuralDBElement
{
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
    #[ORM\OrderBy(['name' => Criteria::ASC])]
    protected Collection $children;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id')]
    #[Groups(['project:read', 'project:write'])]
    #[ApiProperty(readableLink: false, writableLink: false)]
    protected ?AbstractStructuralDBElement $parent = null;

    #[Groups(['project:read', 'project:write'])]
    protected string $comment = '';

    /**
     * @var Collection<int, ProjectBOMEntry>
     */
    #[Assert\Valid(groups: ['project_bom'])]
    #[Groups(['extended', 'full', 'import', 'mcp_project_details:read'])]
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: ProjectBOMEntry::class, cascade: ['persist', 'remove'], orphanRemoval: true, fetch: 'EXTRA_LAZY')]
    #[UniqueObjectCollection(message: 'project.bom_entry.part_already_in_bom', fields: ['part'], groups: ['project_bom'])]
    #[UniqueObjectCollection(message: 'project.bom_entry.name_already_in_bom', fields: ['name'], groups: ['project_bom'])]
    protected Collection $bom_entries;

    #[ORM\Column(type: Types::INTEGER)]
    protected int $order_quantity = 0;

    /**
     * @var string|null The current status of the project
     */
    #[Assert\Choice(choices: ['draft', 'planning', 'in_production', 'finished', 'archived'])]
    #[Groups(['extended', 'full', 'project:read', 'project:write', 'import'])]
    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    protected ?string $status = null;


    /**
     * @var Part|null The (optional) part that represents the builds of this project in the stock
     */
    #[ORM\OneToOne(mappedBy: 'built_project', targetEntity: Part::class, cascade: ['persist'], orphanRemoval: true)]
    #[Groups(['project:read', 'project:write'])]
    protected ?Part $build_part = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $order_only_missing_parts = false;

    #[Groups(['simple', 'extended', 'full', 'project:read', 'project:write'])]
    #[ORM\Column(type: Types::TEXT)]
    protected string $description = '';

    /**
     * @var Collection<int, ProjectAttachment>
     */
    #[ORM\OneToMany(mappedBy: 'element', targetEntity: ProjectAttachment::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['name' => Criteria::ASC])]
    #[Groups(['project:read', 'project:write'])]
    protected Collection $attachments;

    #[ORM\ManyToOne(targetEntity: ProjectAttachment::class)]
    #[ORM\JoinColumn(name: 'id_preview_attachment', onDelete: 'SET NULL')]
    #[Groups(['project:read', 'project:write'])]
    protected ?Attachment $master_picture_attachment = null;

    /** @var Collection<int, ProjectParameter>
     */
    #[ORM\OneToMany(mappedBy: 'element', targetEntity: ProjectParameter::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['group' => Criteria::ASC, 'name' => 'ASC'])]
    #[Groups(['project:read', 'project:write'])]
    protected Collection $parameters;

    #[Groups(['project:read'])]
    protected ?\DateTimeImmutable $addedDate = null;
    #[Groups(['project:read'])]
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
        //When cloning this project, we have to clone each bom entry too.
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
     *  Get the order quantity of this device.
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
    public function addBomEntry(ProjectBOMEntry $entry): self
    {
        $entry->setProject($this);
        $this->bom_entries->add($entry);
        return $this;
    }

    /**
     * @return $this
     */
    public function removeBomEntry(ProjectBOMEntry $entry): self
    {
        $this->bom_entries->removeElement($entry);
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): Project
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
     * Checks if this project has an associated part representing the builds of this project in the stock.
     */
    public function hasBuildPart(): bool
    {
        return $this->build_part instanceof Part;
    }

    /**
     * Gets the part representing the builds of this project in the stock, if it is existing
     */
    public function getBuildPart(): ?Part
    {
        return $this->build_part;
    }

    /**
     * Sets the part representing the builds of this project in the stock.
     */
    public function setBuildPart(?Part $build_part): void
    {
        $this->build_part = $build_part;
        if ($build_part instanceof Part) {
            $build_part->setBuiltProject($this);
        }
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context, $payload): void
    {
        //If this project has subprojects, and these have builds part, they must be included in the BOM
        foreach ($this->getChildren() as $child) {
            if (!$child->getBuildPart() instanceof Part) {
                continue;
            }
            //Use the extra-lazy collection so validating project metadata does
            //not initialize the complete BOM.
            $found = !$this->getBomEntries()->matching(
                Criteria::create()->where(Criteria::expr()->eq('part', $child->getBuildPart()))
            )->isEmpty();

            //When the build part is not found, we have to add an error
            if (!$found) {
                $context->buildViolation('project.bom_has_to_include_all_subelement_parts')
                    ->atPath('bom_entries')
                    ->setParameter('%project_name%', $child->getName())
                    ->setParameter('%part_name%', $child->getBuildPart()->getName())
                    ->addViolation();
            }
        }
    }
}
