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

use App\Repository\DBElementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Base\AbstractDBElement;
use App\Entity\Base\TimestampTrait;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity describes a part association, which is a semantic connection between two parts.
 * For example, a part association can be used to describe that a part is a replacement for another part.
 */
#[ORM\Entity(repositoryClass: DBElementRepository::class)]
#[ORM\HasLifecycleCallbacks]
class PartAssociation extends AbstractDBElement
{
    use TimestampTrait;

    /**
     * @var AssociationType The type of this association (how the two parts are related)
     */
    #[ORM\Column(type: Types::SMALLINT, enumType: AssociationType::class)]
    protected AssociationType $type = AssociationType::OTHER;

    /**
     * @var string|null A user definable association type, which can be described in the comment field, which
     * is used if the type is OTHER
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    protected ?string $other_type = null;

    /**
     * @var string|null A comment describing this association further.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    protected ?string $comment = null;

    /**
     * @var Part|null The part which "owns" this association, e.g. the part which is a replacement for another part
     */
    #[ORM\ManyToOne(targetEntity: Part::class, inversedBy: 'associated_parts_as_owner')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    protected ?Part $owner = null;

    /**
     * @var Part|null The part which is "owned" by this association, e.g. the part which is replaced by another part
     */
    #[ORM\ManyToOne(targetEntity: Part::class, inversedBy: 'associated_parts_as_other')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    protected ?Part $other = null;

    public function getType(): AssociationType
    {
        return $this->type;
    }

    public function setType(AssociationType $type): PartAssociation
    {
        $this->type = $type;
        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): PartAssociation
    {
        $this->comment = $comment;
        return $this;
    }

    public function getOwner(): ?Part
    {
        return $this->owner;
    }

    public function setOwner(?Part $owner): PartAssociation
    {
        $this->owner = $owner;
        return $this;
    }

    public function getOther(): ?Part
    {
        return $this->other;
    }

    public function setOther(?Part $other): PartAssociation
    {
        $this->other = $other;
        return $this;
    }

    public function getOtherType(): ?string
    {
        return $this->other_type;
    }

    public function setOtherType(?string $other_type): PartAssociation
    {
        $this->other_type = $other_type;
        return $this;
    }


}