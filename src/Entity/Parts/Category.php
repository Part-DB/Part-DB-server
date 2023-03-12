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

use App\Entity\Attachments\CategoryAttachment;
use App\Entity\Base\AbstractPartsContainingDBElement;
use App\Entity\Parameters\CategoryParameter;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class AttachmentType.
 *
 * @ORM\Entity(repositoryClass="App\Repository\Parts\CategoryRepository")
 * @ORM\Table(name="`categories`", indexes={
 *     @ORM\Index(name="category_idx_name", columns={"name"}),
 *     @ORM\Index(name="category_idx_parent_name", columns={"parent_id", "name"}),
 * })
 */
class Category extends AbstractPartsContainingDBElement
{
    /**
     * @ORM\OneToMany(targetEntity="Category", mappedBy="parent")
     * @ORM\OrderBy({"name" = "ASC"})
     * @var Collection
     */
    protected $children;

    /**
     * @ORM\ManyToOne(targetEntity="Category", inversedBy="children")
     * @ORM\JoinColumn(name="parent_id", referencedColumnName="id")
     */
    protected $parent;

    /**
     * @var string
     * @ORM\Column(type="text")
     * @Groups({"full", "import"})
     */
    protected string $partname_hint = '';

    /**
     * @var string
     * @ORM\Column(type="text")
     * @Groups({"full", "import"})
     */
    protected string $partname_regex = '';

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     * @Groups({"full", "import"})
     */
    protected bool $disable_footprints = false;

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     * @Groups({"full", "import"})
     */
    protected bool $disable_manufacturers = false;

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     * @Groups({"full", "import"})
     */
    protected bool $disable_autodatasheets = false;

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     * @Groups({"full", "import"})
     */
    protected bool $disable_properties = false;

    /**
     * @var string
     * @ORM\Column(type="text")
     * @Groups({"full", "import"})
     */
    protected string $default_description = '';

    /**
     * @var string
     * @ORM\Column(type="text")
     * @Groups({"full", "import"})
     */
    protected string $default_comment = '';
    /**
     * @var Collection<int, CategoryAttachment>
     * @ORM\OneToMany(targetEntity="App\Entity\Attachments\CategoryAttachment", mappedBy="element", cascade={"persist", "remove"}, orphanRemoval=true)
     * @ORM\OrderBy({"name" = "ASC"})
     * @Assert\Valid()
     * @Groups({"full"})
     */
    protected $attachments;

    /** @var Collection<int, CategoryParameter>
     * @ORM\OneToMany(targetEntity="App\Entity\Parameters\CategoryParameter", mappedBy="element", cascade={"persist", "remove"}, orphanRemoval=true)
     * @ORM\OrderBy({"group" = "ASC" ,"name" = "ASC"})
     * @Assert\Valid()
     * @Groups({"full"})
     */
    protected $parameters;

    public function getPartnameHint(): string
    {
        return $this->partname_hint;
    }

    /**
     * @return Category
     */
    public function setPartnameHint(string $partname_hint): self
    {
        $this->partname_hint = $partname_hint;

        return $this;
    }

    public function getPartnameRegex(): string
    {
        return $this->partname_regex;
    }

    /**
     * @return Category
     */
    public function setPartnameRegex(string $partname_regex): self
    {
        $this->partname_regex = $partname_regex;

        return $this;
    }

    public function isDisableFootprints(): bool
    {
        return $this->disable_footprints;
    }

    /**
     * @return Category
     */
    public function setDisableFootprints(bool $disable_footprints): self
    {
        $this->disable_footprints = $disable_footprints;

        return $this;
    }

    public function isDisableManufacturers(): bool
    {
        return $this->disable_manufacturers;
    }

    /**
     * @return Category
     */
    public function setDisableManufacturers(bool $disable_manufacturers): self
    {
        $this->disable_manufacturers = $disable_manufacturers;

        return $this;
    }

    public function isDisableAutodatasheets(): bool
    {
        return $this->disable_autodatasheets;
    }

    /**
     * @return Category
     */
    public function setDisableAutodatasheets(bool $disable_autodatasheets): self
    {
        $this->disable_autodatasheets = $disable_autodatasheets;

        return $this;
    }

    public function isDisableProperties(): bool
    {
        return $this->disable_properties;
    }

    /**
     * @return Category
     */
    public function setDisableProperties(bool $disable_properties): self
    {
        $this->disable_properties = $disable_properties;

        return $this;
    }

    public function getDefaultDescription(): string
    {
        return $this->default_description;
    }

    /**
     * @return Category
     */
    public function setDefaultDescription(string $default_description): self
    {
        $this->default_description = $default_description;

        return $this;
    }

    public function getDefaultComment(): string
    {
        return $this->default_comment;
    }

    /**
     * @return Category
     */
    public function setDefaultComment(string $default_comment): self
    {
        $this->default_comment = $default_comment;

        return $this;
    }
}
