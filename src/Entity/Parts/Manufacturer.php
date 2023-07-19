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

use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\AttachmentTypeAttachment;
use App\Repository\Parts\ManufacturerRepository;
use App\Entity\Base\AbstractStructuralDBElement;
use Doctrine\Common\Collections\ArrayCollection;
use App\Entity\Attachments\ManufacturerAttachment;
use App\Entity\Base\AbstractCompany;
use App\Entity\Parameters\ManufacturerParameter;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity represents a manufacturer of a part (The company that produces the part).
 *
 * @extends AbstractCompany<ManufacturerAttachment, ManufacturerParameter>
 */
#[ORM\Entity(repositoryClass: ManufacturerRepository::class)]
#[ORM\Table('`manufacturers`')]
#[ORM\Index(name: 'manufacturer_name', columns: ['name'])]
#[ORM\Index(name: 'manufacturer_idx_parent_name', columns: ['parent_id', 'name'])]
class Manufacturer extends AbstractCompany
{
    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id')]
    protected ?AbstractStructuralDBElement $parent = null;

    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    protected Collection $children;

    /**
     * @var Collection<int, ManufacturerAttachment>
     */
    #[Assert\Valid]
    #[ORM\OneToMany(targetEntity: ManufacturerAttachment::class, mappedBy: 'element', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['name' => 'ASC'])]
    protected Collection $attachments;

    #[ORM\OneToOne(targetEntity: ManufacturerAttachment::class)]
    #[ORM\JoinColumn(name: 'id_preview_attachment', onDelete: 'SET NULL')]
    protected ?Attachment $master_picture_attachment = null;

    /** @var Collection<int, ManufacturerParameter>
     */
    #[Assert\Valid]
    #[ORM\OneToMany(targetEntity: ManufacturerParameter::class, mappedBy: 'element', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['group' => 'ASC', 'name' => 'ASC'])]
    protected Collection $parameters;
    public function __construct()
    {
        parent::__construct();
        $this->children = new ArrayCollection();
        $this->attachments = new ArrayCollection();
        $this->parameters = new ArrayCollection();
    }
}
