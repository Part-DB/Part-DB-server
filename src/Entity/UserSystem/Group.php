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

declare(strict_types=1);

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

namespace App\Entity\UserSystem;

use App\Entity\Attachments\GroupAttachment;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Parameters\GroupParameter;
use App\Security\Interfaces\HasPermissionsInterface;
use App\Validator\Constraints\ValidPermission;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity represents an user group.
 *
 * @ORM\Entity()
 * @ORM\Table("`groups`", indexes={
 *     @ORM\Index(name="group_idx_name", columns={"name"}),
 *     @ORM\Index(name="group_idx_parent_name", columns={"parent_id", "name"}),
 * })
 */
class Group extends AbstractStructuralDBElement implements HasPermissionsInterface
{
    /**
     * @ORM\OneToMany(targetEntity="Group", mappedBy="parent")
     * @ORM\OrderBy({"name" = "ASC"})
     * @var Collection
     */
    protected $children;

    /**
     * @ORM\ManyToOne(targetEntity="Group", inversedBy="children")
     * @ORM\JoinColumn(name="parent_id", referencedColumnName="id")
     */
    protected $parent;

    /**
     * @ORM\OneToMany(targetEntity="User", mappedBy="group")
     * @var Collection<User>
     */
    protected $users;

    /**
     * @var bool If true all users associated with this group must have enabled some kind of 2 factor authentication
     * @ORM\Column(type="boolean", name="enforce_2fa")
     */
    protected $enforce2FA = false;
    /**
     * @var Collection<int, GroupAttachment>
     * @ORM\OneToMany(targetEntity="App\Entity\Attachments\GroupAttachment", mappedBy="element", cascade={"persist", "remove"}, orphanRemoval=true)
     * @ORM\OrderBy({"name" = "ASC"})
     * @Assert\Valid()
     */
    protected $attachments;

    /**
     * @var PermissionData
     * @ValidPermission()
     * @ORM\Embedded(class="PermissionData", columnPrefix="permissions_")
     */
    protected PermissionData $permissions;


    /** @var PermissionsEmbed
     * @ORM\Embedded(class="PermissionsEmbed", columnPrefix="perms_")
     */
    protected $permissions_old;

    /** @var Collection<int, GroupParameter>
     * @ORM\OneToMany(targetEntity="App\Entity\Parameters\GroupParameter", mappedBy="element", cascade={"persist", "remove"}, orphanRemoval=true)
     * @ORM\OrderBy({"group" = "ASC" ,"name" = "ASC"})
     * @Assert\Valid()
     */
    protected $parameters;

    public function __construct()
    {
        parent::__construct();
        $this->permissions = new PermissionData();
        $this->permissions_old = new PermissionsEmbed();
        $this->users = new ArrayCollection();
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
        return $this->permissions;
    }

    public function getUsers(): Collection
    {
        return $this->users;
    }
}
