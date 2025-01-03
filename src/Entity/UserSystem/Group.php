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

namespace App\Entity\UserSystem;

use Doctrine\Common\Collections\Criteria;
use App\Entity\Attachments\Attachment;
use App\Validator\Constraints\NoLockout;
use Doctrine\DBAL\Types\Types;
use App\Entity\Attachments\GroupAttachment;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Parameters\GroupParameter;
use App\Security\Interfaces\HasPermissionsInterface;
use App\Validator\Constraints\ValidPermission;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity represents a user group.
 *
 * @extends AbstractStructuralDBElement<GroupAttachment, GroupParameter>
 */
#[ORM\Entity]
#[ORM\Table('`groups`')]
#[ORM\Index(columns: ['name'], name: 'group_idx_name')]
#[ORM\Index(columns: ['parent_id', 'name'], name: 'group_idx_parent_name')]
#[NoLockout]
class Group extends AbstractStructuralDBElement implements HasPermissionsInterface
{
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
    #[ORM\OrderBy(['name' => Criteria::ASC])]
    protected Collection $children;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id')]
    protected ?AbstractStructuralDBElement $parent = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(mappedBy: 'group', targetEntity: User::class)]
    protected Collection $users;

    /**
     * @var bool If true all users associated with this group must have enabled some kind of two-factor authentication
     */
    #[Groups(['extended', 'full', 'import'])]
    #[ORM\Column(name: 'enforce_2fa', type: Types::BOOLEAN)]
    protected bool $enforce2FA = false;

    /**
     * @var Collection<int, GroupAttachment>
     */
    #[Assert\Valid]
    #[ORM\OneToMany(mappedBy: 'element', targetEntity: GroupAttachment::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['name' => Criteria::ASC])]
    protected Collection $attachments;

    #[ORM\ManyToOne(targetEntity: GroupAttachment::class)]
    #[ORM\JoinColumn(name: 'id_preview_attachment', onDelete: 'SET NULL')]
    protected ?Attachment $master_picture_attachment = null;

    #[Groups(['full'])]
    #[ORM\Embedded(class: PermissionData::class, columnPrefix: 'permissions_')]
    #[ValidPermission]
    protected ?PermissionData $permissions = null;

    /**
     * @var Collection<int, GroupParameter>
     */
    #[Assert\Valid]
    #[ORM\OneToMany(mappedBy: 'element', targetEntity: GroupParameter::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['group' => Criteria::ASC, 'name' => 'ASC'])]
    protected Collection $parameters;

    public function __construct()
    {
        $this->attachments = new ArrayCollection();
        $this->parameters = new ArrayCollection();
        parent::__construct();
        $this->permissions = new PermissionData();
        $this->users = new ArrayCollection();
        $this->children = new ArrayCollection();
    }

    /**
     * Check if the users of this group are enforced to have two factor authentification (2FA) enabled.
     */
    public function isEnforce2FA(): bool
    {
        return $this->enforce2FA;
    }

    /**
     * Sets if the user of this group are enforced to have two factor authentification enabled.
     *
     * @param bool $enforce2FA true, if the users of this group are enforced to have 2FA enabled
     *
     * @return $this
     */
    public function setEnforce2FA(bool $enforce2FA): self
    {
        $this->enforce2FA = $enforce2FA;

        return $this;
    }

    public function getPermissions(): PermissionData
    {
        if (!$this->permissions instanceof PermissionData) {
            $this->permissions = new PermissionData();
        }

        return $this->permissions;
    }

    public function getUsers(): Collection
    {
        return $this->users;
    }
}
