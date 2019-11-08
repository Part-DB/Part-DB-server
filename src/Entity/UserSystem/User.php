<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony)
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
 *
 */

declare(strict_types=1);

/**
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/.
 *
 * part-db version 0.2+
 * Copyright (C) 2009 K. Jacobs and others (see authors.php)
 * http://code.google.com/p/part-db/
 *
 * Part-DB Version 0.4+
 * Copyright (C) 2016 - 2019 Jan Böhmer
 * https://github.com/jbtronics
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

use App\Entity\Attachments\AttachmentContainingDBElement;
use App\Entity\Attachments\SupplierAttachment;
use App\Entity\Attachments\UserAttachment;
use App\Entity\Base\NamedDBElement;
use App\Entity\PriceInformations\Currency;
use App\Security\Interfaces\HasPermissionsInterface;
use App\Validator\Constraints\Selectable;
use App\Validator\Constraints\ValidPermission;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity represents a user, which can log in and have permissions.
 * Also this entity is able to save some informations about the user, like the names, email-address and other info.
 * Also this entity is able to save some informations about the user, like the names, email-address and other info.
 *
 * @ORM\Entity(repositoryClass="App\Repository\UserRepository")
 * @ORM\Table("`users`")
 * @UniqueEntity("name", message="validator.user.username_already_used")
 */
class User extends AttachmentContainingDBElement implements UserInterface, HasPermissionsInterface
{
    /** The User id of the anonymous user */
    public const ID_ANONYMOUS = 1;

    public const AVAILABLE_THEMES = ['bootstrap', 'cerulean', 'cosmo', 'cyborg', 'darkly', 'flatly', 'journal',
        'litera', 'lumen', 'lux', 'materia', 'minty', 'pulse', 'sandstone', 'simplex', 'sketchy', 'slate', 'solar',
        'spacelab', 'united', 'yeti'];

    /**
     * @var Collection|UserAttachment[]
     * @ORM\OneToMany(targetEntity="App\Entity\Attachments\UserAttachment", mappedBy="element", cascade={"persist", "remove"}, orphanRemoval=true)
     */
    protected $attachments;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    protected $id;

    /**
     * @ORM\Column(type="string", length=180, unique=true)
     * @Assert\NotBlank
     */
    protected $name = '';

    /**
     * //@ORM\Column(type="json").
     */
    //protected $roles = [];

    /**
     * @var string|null The hashed password
     * @ORM\Column(type="string", nullable=true)
     */
    protected $password;

    /**
     * @var bool True if the user needs to change password after log in
     * @ORM\Column(type="boolean")
     */
    protected $need_pw_change = true;

    /**
     * @var string|null The first name of the User
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $first_name = '';

    /**
     * @var string|null The last name of the User
     * @ORM\Column(type="string", length=255,  nullable=true)
     */
    protected $last_name = '';

    /**
     * @var string|null The department the user is working
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $department = '';

    /**
     * @var string|null The email address of the user
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Assert\Email()
     */
    protected $email = '';

    /**
     * @var string|null The language/locale the user prefers
     * @ORM\Column(type="string", name="config_language", nullable=true)
     * @Assert\Language()
     */
    protected $language = '';

    /**
     * @var string|null The timezone the user prefers
     * @ORM\Column(type="string", name="config_timezone", nullable=true)
     * @Assert\Timezone()
     */
    protected $timezone = '';

    /**
     * @var string|null The theme
     * @ORM\Column(type="string", name="config_theme", nullable=true)
     * @Assert\Choice(choices=User::AVAILABLE_THEMES)
     */
    protected $theme = '';

    /**
     * @var Group|null the group this user belongs to
     * @ORM\ManyToOne(targetEntity="Group", inversedBy="users", fetch="EAGER")
     * @ORM\JoinColumn(name="group_id", referencedColumnName="id")
     * @Selectable()
     */
    protected $group;

    /**
     * @var array
     * @ORM\Column(type="json")
     */
    protected $settings = [];

    /**
     * @var Currency|null The currency the user wants to see prices in.
     * Dont use fetch=EAGER here, this will cause problems with setting the currency setting.
     * TODO: This is most likely a bug in doctrine/symfony related to the UniqueEntity constraint (it makes a db call).
     * TODO: Find a way to use fetch EAGER (this improves performance a bit)
     * @ORM\ManyToOne(targetEntity="App\Entity\PriceInformations\Currency")
     * @ORM\JoinColumn(name="currency_id", referencedColumnName="id")
     * @Selectable()
     */
    protected $currency = null;

    /** @var PermissionsEmbed
     * @ORM\Embedded(class="PermissionsEmbed", columnPrefix="perms_")
     * @ValidPermission()
     */
    protected $permissions;

    /**
     * @ORM\Column(type="text", name="config_instock_comment_w")
     */
    protected $instock_comment_w = '';

    /**
     * @ORM\Column(type="text", name="config_instock_comment_a")
     */
    protected $instock_comment_a = '';

    /**
     * @var string|null The hash of a token the user must provide when he wants to reset his password.
     * @ORM\Column(type="string", nullable=true)
     */
    protected $pw_reset_token = null;

    /**
     * @var \DateTime The time until the password reset token is valid.
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $pw_reset_expires = null;

    /**
     * @var bool Determines if the user is disabled (user can not log in)
     * @ORM\Column(type="boolean")
     */
    protected $disabled = false;


    public function __construct()
    {
        parent::__construct();
        $this->permissions = new PermissionsEmbed();
    }

    /**
     * Checks if the current user, is the user which represents the not logged in (anonymous) users.
     *
     * @return bool true if this user is the anonymous user
     */
    public function isAnonymousUser(): bool
    {
        return $this->id === static::ID_ANONYMOUS && 'anonymous' === $this->name;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUsername(): string
    {
        return (string) $this->name;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = [];
        //$roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        //$this->roles = $roles;

        return $this;
    }

    /**
     * @see UserInterface
     * Gets the password hash for this entity.
     */
    public function getPassword(): string
    {
        return (string) $this->password;
    }

    /**
     * Sets the password hash for this user.
     *
     * @param string $password
     *
     * @return User
     */
    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getSalt()
    {
        // not needed when using the "bcrypt" algorithm in security.yaml
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    /**
     * Gets the currency the user prefers when showing him prices.
     * @return Currency|null The currency the user prefers, or null if the global currency should be used.
     */
    public function getCurrency(): ?Currency
    {
        return $this->currency;
    }

    /**
     * Sets the currency the users prefers to see prices in.
     * @param Currency|null $currency
     * @return User
     */
    public function setCurrency(?Currency $currency): User
    {
        $this->currency = $currency;
        return $this;
    }

    /**
     * Checks if this user is disabled (user cannot login any more).
     * @return bool True, if the user is disabled.
     */
    public function isDisabled(): bool
    {
        return $this->disabled;
    }

    /**
     * Sets the status if a user is disabled.
     * @param bool $disabled True if the user should be disabled.
     * @return User
     */
    public function setDisabled(bool $disabled): User
    {
        $this->disabled = $disabled;
        return $this;
    }



    /**
     * Returns the ID as an string, defined by the element class.
     * This should have a form like P000014, for a part with ID 14.
     *
     * @return string The ID as a string;
     */
    public function getIDString(): string
    {
        return 'U'.sprintf('%06d', $this->getID());
    }

    public function getPermissions(): PermissionsEmbed
    {
        return $this->permissions;
    }

    /**
     * Check if the user needs a password change
     * @return bool
     */
    public function isNeedPwChange(): bool
    {
        return $this->need_pw_change;
    }

    /**
     * Set the status, if the user needs a password change.
     * @param bool $need_pw_change
     * @return User
     */
    public function setNeedPwChange(bool $need_pw_change): User
    {
        $this->need_pw_change = $need_pw_change;
        return $this;
    }

    /************************************************
     * Getters
     ************************************************/



    /**
     * Returns the full name in the format FIRSTNAME LASTNAME [(USERNAME)].
     * Example: Max Muster (m.muster).
     *
     * @param bool $including_username include the username in the full name
     *
     * @return string a string with the full name of this user
     */
    public function getFullName(bool $including_username = false): string
    {
        if ($including_username) {
            return sprintf('%s %s (%s)', $this->getFirstName(), $this->getLastName(), $this->getName());
        }

        return sprintf('%s %s', $this->getFirstName(), $this->getLastName());
    }

    public function setName(string $new_name): NamedDBElement
    {
        // Anonymous user is not allowed to change its username
        if (!$this->isAnonymousUser()) {
            $this->name = $new_name;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getFirstName(): ?string
    {
        return $this->first_name;
    }

    /**
     * @param string $first_name
     *
     * @return User
     */
    public function setFirstName(?string $first_name): User
    {
        $this->first_name = $first_name;

        return $this;
    }

    /**
     * @return string
     */
    public function getLastName(): ?string
    {
        return $this->last_name;
    }

    /**
     * @param string $last_name
     *
     * @return User
     */
    public function setLastName(?string $last_name): User
    {
        $this->last_name = $last_name;

        return $this;
    }

    /**
     * @return string
     */
    public function getDepartment(): ?string
    {
        return $this->department;
    }

    /**
     * @param string $department
     *
     * @return User
     */
    public function setDepartment(?string $department): User
    {
        $this->department = $department;

        return $this;
    }

    /**
     * @return string
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * @param string $email
     *
     * @return User
     */
    public function setEmail(?string $email): User
    {
        $this->email = $email;

        return $this;
    }

    /**
     * @return string
     */
    public function getLanguage(): ?string
    {
        return $this->language;
    }

    /**
     * @param string $language
     *
     * @return User
     */
    public function setLanguage(?string $language): User
    {
        $this->language = $language;

        return $this;
    }

    /**
     * @return string
     */
    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    /**
     * @param string $timezone
     *
     * @return User
     */
    public function setTimezone(?string $timezone): User
    {
        $this->timezone = $timezone;

        return $this;
    }

    /**
     * @return string
     */
    public function getTheme(): ?string
    {
        return $this->theme;
    }

    /**
     * @param string $theme
     *
     * @return User
     */
    public function setTheme(?string $theme): User
    {
        $this->theme = $theme;

        return $this;
    }

    public function getGroup(): ?Group
    {
        return $this->group;
    }

    public function setGroup(?Group $group): self
    {
        $this->group = $group;

        return $this;
    }

    public function __toString()
    {
        $tmp = $this->isDisabled() ? ' [DISABLED]' : '';
        return $this->getFullName(true) . $tmp;
    }
}
