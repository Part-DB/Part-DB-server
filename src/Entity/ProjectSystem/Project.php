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
use ApiPlatform\Serializer\Filter\PropertyFilter;
use App\ApiPlatform\Filter\LikeFilter;
use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\AttachmentTypeAttachment;
use App\Entity\Parts\Category;
use App\Repository\Parts\DeviceRepository;
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
    ],
    normalizationContext: ['groups' => ['project:read', 'api:basic:read'], 'openapi_definition_name' => 'Read'],
    denormalizationContext: ['groups' => ['project:write', 'api:basic:write'], 'openapi_definition_name' => 'Write'],
)]
#[ApiResource(
    uriTemplate: '/projects/{id}/children.{_format}',
    operations: [
        new GetCollection(openapiContext: ['summary' => 'Retrieves the children elements of a project.'],
            security: 'is_granted("@projects.read")')
    ],
    uriVariables: [
        'id' => new Link(fromProperty: 'children', fromClass: Project::class)
    ],
    normalizationContext: ['groups' => ['project:read', 'api:basic:read'], 'openapi_definition_name' => 'Read']
)]
#[ApiFilter(PropertyFilter::class)]
#[ApiFilter(LikeFilter::class, properties: ["name", "comment"])]
#[ApiFilter(OrderFilter::class, properties: ['name', 'id', 'addedDate', 'lastModified'])]
class Project extends AbstractStructuralDBElement
{
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    protected Collection $children;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id')]
    #[Groups(['project:read', 'project:write'])]
    #[ApiProperty(readableLink: false, writableLink: false)]
    protected ?AbstractStructuralDBElement $parent = null;

    #[Groups(['project:read', 'project:write'])]
    protected string $comment = '';

    #[Assert\Valid]
    #[Groups(['extended', 'full'])]
    #[ORM\OneToMany(targetEntity: ProjectBOMEntry::class, mappedBy: 'project', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[UniqueObjectCollection(fields: ['part'], message: 'project.bom_entry.part_already_in_bom')]
    #[UniqueObjectCollection(fields: ['name'], message: 'project.bom_entry.name_already_in_bom')]
    protected Collection $bom_entries;

    #[ORM\Column(type: Types::INTEGER)]
    protected int $order_quantity = 0;

    /**
     * @var string|null The current status of the project
     */
    #[Assert\Choice(['draft', 'planning', 'in_production', 'finished', 'archived'])]
    #[Groups(['extended', 'full', 'project:read', 'project:write'])]
    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    protected ?string $status = null;


    /**
     * @var Part|null The (optional) part that represents the builds of this project in the stock
     */
    #[ORM\OneToOne(targetEntity: Part::class, mappedBy: 'built_project', cascade: ['persist'], orphanRemoval: true)]
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
    #[ORM\OneToMany(targetEntity: ProjectAttachment::class, mappedBy: 'element', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['name' => 'ASC'])]
    #[Groups(['project:read', 'project:write'])]
    protected Collection $attachments;

    #[ORM\ManyToOne(targetEntity: ProjectAttachment::class)]
    #[ORM\JoinColumn(name: 'id_preview_attachment', onDelete: 'SET NULL')]
    #[Groups(['project:read', 'project:write'])]
    protected ?Attachment $master_picture_attachment = null;

    /** @var Collection<int, ProjectParameter>
     */
    #[ORM\OneToMany(targetEntity: ProjectParameter::class, mappedBy: 'element', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['group' => 'ASC', 'name' => 'ASC'])]
    #[Groups(['project:read', 'project:write'])]
    protected Collection $parameters;

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
                $this->bom_entries->add($clone);
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
            /** @var $child Project */
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
                $context->buildViolation('project.bom_has_to_include_all_subelement_parts')
                    ->atPath('bom_entries')
                    ->setParameter('%project_name%', $child->getName())
                    ->setParameter('%part_name%', $child->getBuildPart()->getName())
                    ->addViolation();
            }
        }
    }
}
