<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Entity\LabelSystem;

use App\Entity\Attachments\AttachmentContainingDBElement;
use App\Entity\Attachments\LabelAttachment;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Repository\LabelProfileRepository")
 * @ORM\Table(name="label_profiles")
 * @ORM\EntityListeners({"App\EntityListeners\TreeCacheInvalidationListener"})
 * @UniqueEntity({"name", "options.supported_element"})
 * @package App\Entity\LabelSystem
 */
class LabelProfile extends AttachmentContainingDBElement
{
    /**
     * @var Collection<int, LabelAttachment>
     * @ORM\OneToMany(targetEntity="App\Entity\Attachments\LabelAttachment", mappedBy="element", cascade={"persist", "remove"}, orphanRemoval=true)
     * @ORM\OrderBy({"name" = "ASC"})
     */
    protected $attachments;

    /**
     * @var LabelOptions
     * @ORM\Embedded(class="LabelOptions")
     * @Assert\Valid()
     */
    protected $options;

    /**
     * @var string The comment info for this element
     * @ORM\Column(type="text")
     */
    protected $comment = '';

    public function __construct()
    {
        parent::__construct();
        $this->options = new LabelOptions();
    }

    public function getOptions(): LabelOptions
    {
        return $this->options;
    }

    /**
     * Get the comment of the element.
     * @return string the comment
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(string $new_comment): string
    {
        $this->comment = $new_comment;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getIDString(): string
    {
        return 'LP'.sprintf('%09d', $this->getID());
    }
}