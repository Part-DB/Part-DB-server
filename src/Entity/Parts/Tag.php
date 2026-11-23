<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\McpToolCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use App\ApiPlatform\Filter\LikeFilter;
use App\Entity\AlternativeNames\TagAlternativeName;
use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\TagAttachment;
use App\Entity\Attribute\AdminCrud;
use App\Entity\Attribute\EntityConfig;
use App\Entity\Base\AbstractPartsContainingDBElement;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Parameters\TagParameter;
use App\Form\AdminPages\TagAdminForm;
use App\Helpers\BootstrapColor;
use App\Mcp\DTO\ElementByIdInput;
use App\Mcp\DTO\StructuralElementOverview;
use App\Mcp\DTO\StructuralElementSearchInput;
use App\Repository\Parts\TagRepository;
use App\State\Mcp\GetStructuralElementDetailsProcessor;
use App\State\Mcp\ListStructuralElementsProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SortDirection;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A tag, which can be assigned to any number of parts.
 *
 * Tags used to be a comma-separated text on every part. As entities of their own, parts refer to the same tag, so a
 * tag can be renamed or styled in one place, and tags can be grouped in a hierarchy like the other data structures.
 *
 * Unlike the other data structures, a tag name is unique across all tags, not only among its siblings: tags are still
 * assigned by their names (see Part::setTags()), so a name must stand for exactly one tag. Names are compared
 * case-insensitively, so "SMD" and "smd" are the same tag. The parent only groups tags, and passes its color on.
 *
 * @see \App\Tests\Entity\Parts\TagTest
 * @extends AbstractPartsContainingDBElement<TagAttachment, TagParameter, TagAlternativeName>
 */
#[EntityConfig(routeBase: 'tag')]
#[AdminCrud(formClass: TagAdminForm::class, showParametersTab: false)]
#[ORM\Entity(repositoryClass: TagRepository::class)]
#[ORM\Table('`tags`')]
#[ORM\UniqueConstraint(name: 'tag_name_unique', columns: ['name'])]
#[UniqueEntity(fields: ['name'], message: 'tag.name.not_unique', repositoryMethod: 'findForUniquenessCheck')]
#[ApiResource(
    operations: [
        new Get(security: 'is_granted("read", object)'),
        new GetCollection(security: 'is_granted("@tags.read")'),
        new Post(securityPostDenormalize: 'is_granted("create", object)'),
        new Patch(security: 'is_granted("edit", object)'),
        new Delete(security: 'is_granted("delete", object)'),
    ],
    normalizationContext: ['groups' => ['tag:read', 'api:basic:read'], 'openapi_definition_name' => 'Read'],
    denormalizationContext: ['groups' => ['tag:write', 'api:basic:write'], 'openapi_definition_name' => 'Write'],
    mcp: [
        'list_tags' => new McpToolCollection(
            title: 'List/search tags',
            description: 'List all tags, optionally filtered by a keyword matched against the name and comment. Tags are assigned to parts to group them, a part can have any number of tags. Each entry includes its full hierarchical path, and results are sorted by that path so parents are immediately followed by their own children, making it easy to derive the tree structure from the flat list.',
            annotations: ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false],
            normalizationContext: ['groups' => ['mcp_structural_overview:read']],
            security: 'is_granted("@tags.read")',
            input: StructuralElementSearchInput::class,
            output: StructuralElementOverview::class,
            processor: ListStructuralElementsProcessor::class,
        ),
        'get_tag_details' => new McpTool(
            title: 'Get tag details by ID',
            description: 'Get detailed information about a specific tag by its database ID.',
            annotations: ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false],
            normalizationContext: ['groups' => ['tag:read', 'api:basic:read']],
            security: 'is_granted("@tags.read")',
            input: ElementByIdInput::class,
            validate: true,
            processor: GetStructuralElementDetailsProcessor::class,
        ),
    ],
)]
#[ApiFilter(PropertyFilter::class)]
#[ApiFilter(LikeFilter::class, properties: ["name", "comment"])]
#[ApiFilter(DateFilter::class, strategy: DateFilterInterface::EXCLUDE_NULL)]
#[ApiFilter(OrderFilter::class, properties: ['name', 'id', 'addedDate', 'lastModified'])]
class Tag extends AbstractPartsContainingDBElement
{
    /**
     * Tags are assigned and transferred as a comma-separated list of names (see Part::setTags()), so a comma can not
     * be part of a tag name.
     */
    public const SEPARATOR = ',';

    #[Assert\Regex(pattern: '/,/', message: 'tag.name.no_comma', match: false)]
    protected string $name = '';

    /**
     * @var string The comment info for this element as markdown
     */
    #[Groups(['tag:read', 'tag:write', 'full', 'import'])]
    protected string $comment = '';

    /**
     * @var BootstrapColor|null The semantic color the tag is rendered as a badge with.
     * Null takes the color of the parent tag, and keeps the appearance tags had before they could be colored if no
     * tag above it has a color either.
     */
    #[ORM\Column(type: Types::STRING, length: 20, nullable: true, enumType: BootstrapColor::class)]
    #[Groups(['tag:read', 'tag:write', 'full', 'import'])]
    protected ?BootstrapColor $color = null;

    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent', cascade: ['persist'])]
    #[ORM\OrderBy(['name' => SortDirection::Ascending])]
    protected Collection $children;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id')]
    #[Groups(['tag:read', 'tag:write'])]
    #[ApiProperty(readableLink: false, writableLink: false)]
    protected ?AbstractStructuralDBElement $parent = null;

    /**
     * @var Collection<int, TagAttachment>
     */
    #[Assert\Valid]
    #[ORM\OneToMany(targetEntity: TagAttachment::class, mappedBy: 'element', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['name' => SortDirection::Ascending])]
    #[Groups(['tag:read', 'tag:write'])]
    protected Collection $attachments;

    #[ORM\ManyToOne(targetEntity: TagAttachment::class)]
    #[ORM\JoinColumn(name: 'id_preview_attachment', onDelete: 'SET NULL')]
    #[Groups(['tag:read', 'tag:write'])]
    protected ?Attachment $master_picture_attachment = null;

    /** @var Collection<int, TagParameter>
     */
    #[Assert\Valid]
    #[ORM\OneToMany(targetEntity: TagParameter::class, mappedBy: 'element', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['name' => SortDirection::Ascending])]
    #[Groups(['tag:read', 'tag:write'])]
    protected Collection $parameters;

    #[ORM\OneToMany(targetEntity: TagAlternativeName::class, mappedBy: 'element', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sort_index' => 'ASC', 'name' => 'ASC'])]
    protected Collection $alternative_names;

    #[Groups(['tag:read'])]
    protected ?\DateTimeImmutable $addedDate = null;
    #[Groups(['tag:read'])]
    protected ?\DateTimeImmutable $lastModified = null;

    public function __construct(string $name = '')
    {
        parent::__construct();
        $this->children = new ArrayCollection();
        $this->attachments = new ArrayCollection();
        $this->parameters = new ArrayCollection();
        $this->alternative_names = new ArrayCollection();
        $this->name = self::normalizeName($name);
    }

    public function setName(string $new_name): self
    {
        $this->name = self::normalizeName($new_name);

        return $this;
    }

    public function getColor(): ?BootstrapColor
    {
        return $this->color;
    }

    public function setColor(?BootstrapColor $color): self
    {
        $this->color = $color;

        return $this;
    }

    /**
     * Returns the color the tag is shown with: its own color, or the color of the nearest tag above it which has one.
     */
    public function getEffectiveColor(): ?BootstrapColor
    {
        if ($this->color !== null) {
            return $this->color;
        }

        return $this->parent instanceof self ? $this->parent->getEffectiveColor() : null;
    }

    /**
     * Returns the CSS class this tag is rendered as a badge with.
     * Without a color (also none inherited), the class the calling place used before tags could be colored is
     * returned, so an unconfigured tag keeps looking exactly the way it did.
     */
    public function getBadgeClass(string $default = 'bg-primary'): string
    {
        return $this->getEffectiveColor()?->toBadgeClass() ?? $default;
    }

    /**
     * Brings a tag name into the form it is stored in: without surrounding whitespace and with inner whitespace
     * collapsed, so that "SMD " and "SMD" do not end up as two tags.
     */
    public static function normalizeName(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }

    /**
     * Returns the key two tag names are compared by. Tags whose names have the same key are the same tag.
     */
    public static function nameKey(string $name): string
    {
        return mb_strtolower(self::normalizeName($name));
    }

    /**
     * Splits a comma-separated list of tag names into the distinct, normalized names it contains, keeping the
     * first spelling of names which only differ in case.
     * @return list<string>
     */
    public static function splitNames(string $names): array
    {
        $result = [];
        foreach (explode(self::SEPARATOR, $names) as $name) {
            $name = self::normalizeName($name);
            if ($name !== '' && !isset($result[self::nameKey($name)])) {
                $result[self::nameKey($name)] = $name;
            }
        }

        return array_values($result);
    }
}
