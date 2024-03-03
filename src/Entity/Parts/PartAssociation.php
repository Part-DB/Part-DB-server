<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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

declare(strict_types=1);


namespace App\Entity\Parts;

use ApiPlatform\Doctrine\Common\Filter\DateFilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use App\ApiPlatform\Filter\LikeFilter;
use App\Entity\Contracts\TimeStampableInterface;
use App\Repository\DBElementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AbstractDBElement;
use App\Entity\Base\TimestampTrait;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity describes a part association, which is a semantic connection between two parts.
 * For example, a part association can be used to describe that a part is a replacement for another part.
 */
#[ORM\Entity(repositoryClass: DBElementRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['other', 'owner', 'type'], message: 'validator.part_association.already_exists')]
#[ApiResource(
    operations: [
        new Get(security: 'is_granted("read", object)'),
        new GetCollection(security: 'is_granted("@parts.read")'),
        new Post(securityPostDenormalize: 'is_granted("create", object)'),
        new Patch(security: 'is_granted("edit", object)'),
        new Delete(security: 'is_granted("delete", object)'),
    ],
    normalizationContext: ['groups' => ['part_assoc:read', 'part_assoc:read:standalone',  'api:basic:read'], 'openapi_definition_name' => 'Read'],
    denormalizationContext: ['groups' => ['part_assoc:write', 'api:basic:write'], 'openapi_definition_name' => 'Write'],
)]
#[ApiFilter(PropertyFilter::class)]
#[ApiFilter(LikeFilter::class, properties: ["other_type", "comment"])]
#[ApiFilter(DateFilter::class, strategy: DateFilterInterface::EXCLUDE_NULL)]
#[ApiFilter(OrderFilter::class, properties: ['comment', 'addedDate', 'lastModified'])]
class PartAssociation extends AbstractDBElement implements TimeStampableInterface
{
    use TimestampTrait;

    /**
     * @var AssociationType The type of this association (how the two parts are related)
     */
    #[ORM\Column(type: Types::SMALLINT, enumType: AssociationType::class)]
    #[Groups(['part_assoc:read', 'part_assoc:write'])]
    protected AssociationType $type = AssociationType::OTHER;

    /**
     * @var string|null A user definable association type, which can be described in the comment field, which
     * is used if the type is OTHER
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Expression("this.getType().value !== 0 or this.getOtherType() !== null",
        message: 'validator.part_association.must_set_an_value_if_type_is_other')]
    #[Groups(['part_assoc:read', 'part_assoc:write'])]
    protected ?string $other_type = null;

    /**
     * @var string|null A comment describing this association further.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['part_assoc:read', 'part_assoc:write'])]
    protected ?string $comment = null;

    /**
     * @var Part|null The part which "owns" this association, e.g. the part which is a replacement for another part
     */
    #[ORM\ManyToOne(targetEntity: Part::class, inversedBy: 'associated_parts_as_owner')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    #[Groups(['part_assoc:read:standalone', 'part_assoc:write'])]
    protected ?Part $owner = null;

    /**
     * @var Part|null The part which is "owned" by this association, e.g. the part which is replaced by another part
     */
    #[ORM\ManyToOne(targetEntity: Part::class, inversedBy: 'associated_parts_as_other')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    #[Assert\Expression("this.getOwner() !== this.getOther()",
        message: 'validator.part_association.part_cannot_be_associated_with_itself')]
    #[Groups(['part_assoc:read', 'part_assoc:write'])]
    protected ?Part $other = null;

    /**
     * Returns the (semantic) relation type of this association as an AssociationType enum value.
     * If the type is set to OTHER, then the other_type field value is used for the user defined type.
     * @return AssociationType
     */
    public function getType(): AssociationType
    {
        return $this->type;
    }

    /**
     * Sets the (semantic) relation type of this association as an AssociationType enum value.
     * @param  AssociationType  $type
     * @return $this
     */
    public function setType(AssociationType $type): PartAssociation
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Returns a comment, which describes this association further.
     * @return string|null
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * Sets a comment, which describes this association further.
     * @param  string|null  $comment
     * @return $this
     */
    public function setComment(?string $comment): PartAssociation
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * Returns the part which "owns" this association, e.g. the part which is a replacement for another part.
     * @return Part|null
     */
    public function getOwner(): ?Part
    {
        return $this->owner;
    }

    /**
     * Sets the part which "owns" this association, e.g. the part which is a replacement for another part.
     * @param  Part|null  $owner
     * @return $this
     */
    public function setOwner(?Part $owner): PartAssociation
    {
        $this->owner = $owner;
        return $this;
    }

    /**
     * Returns the part which is "owned" by this association, e.g. the part which is replaced by another part.
     * @return Part|null
     */
    public function getOther(): ?Part
    {
        return $this->other;
    }

    /**
     * Sets the part which is "owned" by this association, e.g. the part which is replaced by another part.
     * @param  Part|null  $other
     * @return $this
     */
    public function setOther(?Part $other): PartAssociation
    {
        $this->other = $other;
        return $this;
    }

    /**
     * Returns the user defined association type, which is used if the type is set to OTHER.
     * @return string|null
     */
    public function getOtherType(): ?string
    {
        return $this->other_type;
    }

    /**
     * Sets the user defined association type, which is used if the type is set to OTHER.
     * @param  string|null  $other_type
     * @return $this
     */
    public function setOtherType(?string $other_type): PartAssociation
    {
        $this->other_type = $other_type;
        return $this;
    }

    /**
     * Returns the translation key for the type of this association.
     * If the type is set to OTHER, then the other_type field value is used.
     * @return string
     */
    public function getTypeTranslationKey(): string
    {
        if ($this->type === AssociationType::OTHER) {
            return $this->other_type ?? 'Unknown';
        }
        return $this->type->getTranslationKey();
    }

}