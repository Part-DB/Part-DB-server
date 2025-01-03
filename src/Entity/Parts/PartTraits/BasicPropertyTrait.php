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

namespace App\Entity\Parts\PartTraits;

use Doctrine\DBAL\Types\Types;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Validator\Constraints\Selectable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

trait BasicPropertyTrait
{
    /**
     * @var string A text describing what this part does
     */
    #[Groups(['simple', 'extended', 'full', 'import', 'part:read', 'part:write'])]
    #[ORM\Column(type: Types::TEXT)]
    protected string $description = '';

    /**
     * @var string A comment/note related to this part
     */
    #[Groups(['extended', 'full', 'import', 'part:read', 'part:write'])]
    #[ORM\Column(type: Types::TEXT)]
    protected string $comment = '';

    /**
     * @var bool Kept for compatibility (it is not used now, and I don't think it was used in old versions)
     */
    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $visible = true;

    /**
     * @var bool true, if the part is marked as favorite
     */
    #[Groups(['extended', 'full', 'import', 'part:read', 'part:write'])]
    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $favorite = false;

    /**
     * @var Category|null The category this part belongs too (e.g. Resistors). Use tags, for more complex grouping.
     *               Every part must have a category.
     */
    #[Assert\NotNull(message: 'validator.select_valid_category')]
    #[Selectable]
    #[Groups(['simple', 'extended', 'full', 'import', "part:read", "part:write"])]
    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'id_category', nullable: false)]
    protected ?Category $category = null;

    /**
     * @var Footprint|null The footprint of this part (e.g. DIP8)
     */
    #[Groups(['simple', 'extended', 'full', 'import', 'part:read', 'part:write'])]
    #[ORM\ManyToOne(targetEntity: Footprint::class)]
    #[ORM\JoinColumn(name: 'id_footprint')]
    #[Selectable]
    protected ?Footprint $footprint = null;

    /**
     * Get the description string like it is saved in the database.
     * This can contain BBCode, it is not parsed yet.
     *
     * @return string the description
     */
    public function getDescription(): string
    {
        return  $this->description;
    }

    /**
     * Get the comment associated with this part.
     *
     * @return string The raw/unparsed comment
     */
    public function getComment(): string
    {
        return $this->comment;
    }

    /**
     * Get if this part is visible.
     * This property is not used yet.
     *
     * @return bool true if this part is visible
     *              false if this part isn't visible
     */
    public function isVisible(): bool
    {
        return $this->visible;
    }

    /**
     * Check if this part is a favorite.
     *
     * @return bool * true if this part is a favorite
     *              * false if this part is not a favorite
     */
    public function isFavorite(): bool
    {
        return $this->favorite;
    }

    /**
     * Get the category of this part (e.g. Resistors).
     * There is always a category, for each part!
     *
     * @return Category the category of this part
     */
    public function getCategory(): ?Category
    {
        return $this->category;
    }

    /**
     * Gets the Footprint of this part (e.g. DIP8).
     *
     * @return Footprint|null The footprint of this part. Null if this part should not have a footprint.
     */
    public function getFootprint(): ?Footprint
    {
        return $this->footprint;
    }

    /**
     * Sets the description of this part.
     *
     * @param  string|null  $new_description  the new description
     *
     * @return $this
     */
    public function setDescription(?string $new_description): self
    {
        $this->description = $new_description;

        return $this;
    }

    /**
     * Sets the comment property of this part.
     *
     * @param string $new_comment the new comment
     *
     * @return $this
     */
    public function setComment(string $new_comment): self
    {
        $this->comment = $new_comment;

        return $this;
    }

    /**
     * Set the category of this Part.
     * The category property is required for every part, so you can not pass null like the other properties (footprints).
     *
     * @param  Category|null  $category  The new category of this part
     *
     * @return $this
     */
    public function setCategory(?Category $category): self
    {
        $this->category = $category;

        return $this;
    }

    /**
     * Set the new Footprint of this Part.
     *
     * @param Footprint|null $new_footprint The new footprint of this part. Set to null, if this part should not have
     *                                      a footprint.
     *
     * @return $this
     */
    public function setFootprint(?Footprint $new_footprint): self
    {
        $this->footprint = $new_footprint;

        return $this;
    }

    /**
     * Set the favorite status for this part.
     *
     * @param bool $new_favorite_status The new favorite status, that should be applied on this part.
     *                                  Set this to true, when the part should be a favorite.
     *
     * @return $this
     */
    public function setFavorite(bool $new_favorite_status): self
    {
        $this->favorite = $new_favorite_status;

        return $this;
    }
}
