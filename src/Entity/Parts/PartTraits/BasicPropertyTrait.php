<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Entity\Parts\PartTraits;

use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Part;
use App\Security\Annotations\ColumnSecurity;
use App\Validator\Constraints\Selectable;

trait BasicPropertyTrait
{
    /**
     * @var string A text describing what this part does
     * @ORM\Column(type="text")
     * @ColumnSecurity(prefix="description")
     */
    protected $description = '';

    /**
     * @var string A comment/note related to this part
     * @ORM\Column(type="text")
     * @ColumnSecurity(prefix="comment")
     */
    protected $comment = '';

    /**
     * @var bool Kept for compatibility (it is not used now, and I dont think it was used in old versions)
     * @ORM\Column(type="boolean")
     */
    protected $visible = true;

    /**
     * @var bool True, if the part is marked as favorite.
     * @ORM\Column(type="boolean")
     * @ColumnSecurity(type="boolean")
     */
    protected $favorite = false;

    /**
     * @var Category The category this part belongs too (e.g. Resistors). Use tags, for more complex grouping.
     *               Every part must have a category.
     * @ORM\ManyToOne(targetEntity="Category", inversedBy="parts")
     * @ORM\JoinColumn(name="id_category", referencedColumnName="id", nullable=false)
     * @ColumnSecurity(prefix="category", type="App\Entity\Parts\Category")
     * @Selectable()
     */
    protected $category;

    /**
     * @var Footprint|null The footprint of this part (e.g. DIP8)
     * @ORM\ManyToOne(targetEntity="Footprint", inversedBy="parts")
     * @ORM\JoinColumn(name="id_footprint", referencedColumnName="id")
     * @ColumnSecurity(prefix="footprint", type="App\Entity\Parts\Footprint")
     * @Selectable()
     */
    protected $footprint;

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
     *              * false if this part is not a favorite.
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
     * @return Footprint|null The footprint of this part. Null if this part should no have a footprint.
     */
    public function getFootprint(): ?Footprint
    {
        return $this->footprint;
    }

    /**
     * Sets the description of this part.
     *
     * @param string $new_description the new description
     *
     * @return self
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
     * @return self
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
     * @param Category $category The new category of this part
     *
     * @return self
     */
    public function setCategory(Category $category): self
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
     * @return self
     */
    public function setFootprint(?Footprint $new_footprint): self
    {
        $this->footprint = $new_footprint;

        return $this;
    }

    /**
     * Set the favorite status for this part.
     *
     * @param $new_favorite_status bool The new favorite status, that should be applied on this part.
     *      Set this to true, when the part should be a favorite.
     *
     * @return self
     */
    public function setFavorite(bool $new_favorite_status): self
    {
        $this->favorite = $new_favorite_status;

        return $this;
    }
}
