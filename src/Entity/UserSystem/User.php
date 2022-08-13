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
use App\Entity\Attachments\UserAttachment;
use App\Entity\Base\AbstractNamedDBElement;
use App\Entity\PriceInformations\Currency;
use App\Security\Interfaces\HasPermissionsInterface;
use App\Validator\Constraints\Selectable;
use App\Validator\Constraints\ValidPermission;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use function count;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use function in_array;
//use R\U2FTwoFactorBundle\Model\U2F\TwoFactorInterface as U2FTwoFactorInterface;
//use R\U2FTwoFactorBundle\Model\U2F\TwoFactorKeyInterface;
use Scheb\TwoFactorBundle\Model\BackupCodeInterface;
use Scheb\TwoFactorBundle\Model\Google\TwoFactorInterface;
use Scheb\TwoFactorBundle\Model\PreferredProviderInterface;
use Scheb\TwoFactorBundle\Model\TrustedDeviceInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity represents a user, which can log in and have permissions.
 * Also this entity is able to save some informations about the user, like the names, email-address and other info.
 *
 * @ORM\Entity(repositoryClass="App\Repository\UserRepository")
 * @ORM\Table("`users`")
 * @ORM\EntityListeners({"App\EntityListeners\TreeCacheInvalidationListener"})
 * @UniqueEntity("name", message="validator.user.username_already_used")
 */
class User extends AttachmentContainingDBElement implements UserInterface, HasPermissionsInterface, TwoFactorInterface, BackupCodeInterface, TrustedDeviceInterface, /*U2FTwoFactorInterface,*/ PreferredProviderInterface, PasswordAuthenticatedUserInterface
{
    //use MasterAttachmentTrait;

    /**
     * The User id of the anonymous user.
     */
    public const ID_ANONYMOUS = 1;

    public const AVAILABLE_THEMES = ['bootstrap', 'cerulean', 'cosmo', 'cyborg', 'darkly', 'flatly', 'journal',
        'litera', 'lumen', 'lux', 'materia', 'minty', 'pulse', 'sandstone', 'simplex', 'sketchy', 'slate', 'solar',
        'spacelab', 'united', 'yeti', ];

    /**
     * @var bool Determines if the user is disabled (user can not log in)
     * @ORM\Column(type="boolean")
     */
    protected $disabled = false;

    /**
     * @var string|null The theme
     * @ORM\Column(type="string", name="config_theme", nullable=true)
     * @Assert\Choice(choices=User::AVAILABLE_THEMES)
     */
    protected $theme = '';

    /**
     * @var string|null the hash of a token the user must provide when he wants to reset his password
     * @ORM\Column(type="string", nullable=true)
     */
    protected $pw_reset_token;

    /**
     * @ORM\Column(type="text", name="config_instock_comment_a")
     */
    protected $instock_comment_a = '';

    /**
     * @ORM\Column(type="text", name="config_instock_comment_w")
     */
    protected $instock_comment_w = '';

    /** @var int The version of the trusted device cookie. Used to invalidate all trusted device cookies at once.
     *  @ORM\Column(type="integer")
     */
    protected $trustedDeviceCookieVersion = 0;

    /**
     * @var string[]|null A list of backup codes that can be used, if the user has no access to its Google Authenticator device
     * @ORM\Column(type="json")
     */
    protected $backupCodes = [];

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    protected $id;

    /**
     * @var Group|null the group this user belongs to
     * @ORM\ManyToOne(targetEntity="Group", inversedBy="users", fetch="EAGER")
     * @ORM\JoinColumn(name="group_id", referencedColumnName="id")
     * @Selectable()
     */
    protected $group;

    /**
     * @var string|null The secret used for google authenticator
     * @ORM\Column(name="google_authenticator_secret", type="string", nullable=true)
     */
    protected $googleAuthenticatorSecret;

    /**
     * @var string|null The timezone the user prefers
     * @ORM\Column(type="string", name="config_timezone", nullable=true)
     * @Assert\Timezone()
     */
    protected $timezone = '';

    /**
     * @var string|null The language/locale the user prefers
     * @ORM\Column(type="string", name="config_language", nullable=true)
     * @Assert\Language()
     */
    protected $language = '';

    /**
     * @var string|null The email address of the user
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Assert\Email()
     */
    protected $email = '';

    /**
     * @var string|null The department the user is working
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $department = '';

    /**
     * @var string|null The last name of the User
     * @ORM\Column(type="string", length=255,  nullable=true)
     */
    protected $last_name = '';

    /**
     * @var string|null The first name of the User
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $first_name = '';

    /**
     * @var bool True if the user needs to change password after log in
     * @ORM\Column(type="boolean")
     */
    protected $need_pw_change = true;

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
     * @ORM\Column(type="string", length=180, unique=true)
     * @Assert\NotBlank
     * @Assert\Regex("/^[\w\.\+\-\$]+$/", message="user.invalid_username")
     */
    protected $name = '';

    /**
     * @var array
     * @ORM\Column(type="json")
     */
    protected $settings = [];

    /**
     * @var Collection<int, UserAttachment>
     * @ORM\OneToMany(targetEntity="App\Entity\Attachments\UserAttachment", mappedBy="element", cascade={"persist", "remove"}, orphanRemoval=true)
     * @ORM\OrderBy({"name" = "ASC"})
     */
    protected $attachments;

    /** @var DateTime|null The time when the backup codes were generated
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $backupCodesGenerationDate;

    /** @var Collection<int, TwoFactorKeyInterface>
     * @ORM\OneToMany(targetEntity="App\Entity\UserSystem\U2FKey", mappedBy="user", cascade={"REMOVE"}, orphanRemoval=true)
     */
    protected $u2fKeys;

    /**
     * @var Currency|null The currency the user wants to see prices in.
     *                    Dont use fetch=EAGER here, this will cause problems with setting the currency setting.
     *                    TODO: This is most likely a bug in doctrine/symfony related to the UniqueEntity constraint (it makes a db call).
     *                    TODO: Find a way to use fetch EAGER (this improves performance a bit)
     * @ORM\ManyToOne(targetEntity="App\Entity\PriceInformations\Currency")
     * @ORM\JoinColumn(name="currency_id", referencedColumnName="id")
     * @Selectable()
     */
    protected $currency;

    /** @var PermissionsEmbed
     * @ORM\Embedded(class="PermissionsEmbed", columnPrefix="perms_")
     * @ValidPermission()
     */
    protected $permissions;

    /**
     * @var DateTime the time until the password reset token is valid
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $pw_reset_expires;

    public function __construct()
    {
        parent::__construct();
        $this->permissions = new PermissionsEmbed();
        $this->u2fKeys = new ArrayCollection();
    }

    /**
     * Returns a string representation of this user (the full name).
     * E.g. 'Jane Doe (j.doe) [DISABLED].
     *
     * @return string
     */
    public function __toString()
    {
        $tmp = $this->isDisabled() ? ' [DISABLED]' : '';

        return $this->getFullName(true).$tmp;
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

    public function getUserIdentifier(): string
    {
        return $this->getUsername();
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
    public function getSalt(): ?string
    {
        return null;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    /**
     * Gets the currency the user prefers when showing him prices.
     *
     * @return Currency|null the currency the user prefers, or null if the global currency should be used
     */
    public function getCurrency(): ?Currency
    {
        return $this->currency;
    }

    /**
     * Sets the currency the users prefers to see prices in.
     *
     * @return User
     */
    public function setCurrency(?Currency $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * Checks if this user is disabled (user cannot login any more).
     *
     * @return bool true, if the user is disabled
     */
    public function isDisabled(): bool
    {
        return $this->disabled;
    }

    /**
     * Sets the status if a user is disabled.
     *
     * @param bool $disabled true if the user should be disabled
     *
     * @return User
     */
    public function setDisabled(bool $disabled): self
    {
        $this->disabled = $disabled;

        return $this;
    }

    public function getPermissions(): PermissionsEmbed
    {
        return $this->permissions;
    }

    /**
     * Check if the user needs a password change.
     */
    public function isNeedPwChange(): bool
    {
        return $this->need_pw_change;
    }

    /**
     * Set the status, if the user needs a password change.
     *
     * @return User
     */
    public function setNeedPwChange(bool $need_pw_change): self
    {
        $this->need_pw_change = $need_pw_change;

        return $this;
    }

    /**
     * Returns the encrypted password reset token.
     */
    public function getPwResetToken(): ?string
    {
        return $this->pw_reset_token;
    }

    /**
     * Sets the encrypted password reset token.
     *
     * @return User
     */
    public function setPwResetToken(?string $pw_reset_token): self
    {
        $this->pw_reset_token = $pw_reset_token;

        return $this;
    }

    /**
     * Gets the datetime when the password reset token expires.
     */
    public function getPwResetExpires(): DateTime
    {
        return $this->pw_reset_expires;
    }

    /**
     * Sets the datetime when the password reset token expires.
     *
     * @return User
     */
    public function setPwResetExpires(DateTime $pw_reset_expires): self
    {
        $this->pw_reset_expires = $pw_reset_expires;

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
        $tmp = $this->getFirstName();
        //Dont add a space, if the name has only one part (it would look strange)
        if (!empty($this->getFirstName()) && !empty($this->getLastName())) {
            $tmp .= ' ';
        }
        $tmp .= $this->getLastName();

        if ($including_username) {
            $tmp .= sprintf(' (@%s)', $this->getName());
        }

        return $tmp;
    }

    /**
     * Change the username of this user.
     *
     * @param string $new_name the new username
     *
     * @return $this
     */
    public function setName(string $new_name): AbstractNamedDBElement
    {
        // Anonymous user is not allowed to change its username
        if (!$this->isAnonymousUser()) {
            $this->name = $new_name;
        }

        return $this;
    }

    /**
     * Get the first name of the user.
     */
    public function getFirstName(): ?string
    {
        return $this->first_name;
    }

    /**
     * Change the first name of the user.
     *
     * @param string $first_name The new first name
     *
     * @return $this
     */
    public function setFirstName(?string $first_name): self
    {
        $this->first_name = $first_name;

        return $this;
    }

    /**
     * Get the last name of the user.
     */
    public function getLastName(): ?string
    {
        return $this->last_name;
    }

    /**
     * Change the last name of the user.
     *
     * @param string $last_name The new last name
     *
     * @return $this
     */
    public function setLastName(?string $last_name): self
    {
        $this->last_name = $last_name;

        return $this;
    }

    /**
     * Gets the department of this user.
     *
     * @return string
     */
    public function getDepartment(): ?string
    {
        return $this->department;
    }

    /**
     * Change the department of the user.
     *
     * @param string $department The new department
     *
     * @return User
     */
    public function setDepartment(?string $department): self
    {
        $this->department = $department;

        return $this;
    }

    /**
     * Get the email of the user.
     *
     * @return string
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * Change the email of the user.
     *
     * @param string $email The new email adress
     *
     * @return $this
     */
    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Gets the language the user prefers (as 2 letter ISO code).
     *
     * @return string|null The 2 letter ISO code of the preferred language (e.g. 'en' or 'de').
     *                     If null is returned, the user has not specified a language and the server wide language should be used.
     */
    public function getLanguage(): ?string
    {
        return $this->language;
    }

    /**
     * Change the language the user prefers.
     *
     * @param string|null $language The new language as 2 letter ISO code (e.g. 'en' or 'de').
     *                              Set to null, to use the system wide language.
     *
     * @return User
     */
    public function setLanguage(?string $language): self
    {
        $this->language = $language;

        return $this;
    }

    /**
     * Gets the timezone of the user.
     *
     * @return string|null The timezone of the user (e.g. 'Europe/Berlin') or null if the user has not specified
     *                     a timezone (then the global one should be used)
     */
    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    /**
     * Change the timezone of this user.
     *
     * @return $this
     */
    public function setTimezone(?string $timezone): self
    {
        $this->timezone = $timezone;

        return $this;
    }

    /**
     * Gets the theme the users wants to see. See self::AVAILABLE_THEMES for valid values.
     *
     * @return string|null the name of the theme the user wants to see, or null if the system wide should be used
     */
    public function getTheme(): ?string
    {
        return $this->theme;
    }

    /**
     * Change the theme the user wants to see.
     *
     * @param string|null $theme The name of the theme (See See self::AVAILABLE_THEMES for valid values). Set to null
     *                           if the system wide theme should be used.
     *
     * @return $this
     */
    public function setTheme(?string $theme): self
    {
        $this->theme = $theme;

        return $this;
    }

    /**
     * Gets the group to which this user belongs to.
     *
     * @return Group|null The group of this user. Null if this user does not have a group.
     */
    public function getGroup(): ?Group
    {
        return $this->group;
    }

    /**
     * Sets the group of this user.
     *
     * @param Group|null $group The new group of this user. Set to null if this user should not have a group.
     *
     * @return $this
     */
    public function setGroup(?Group $group): self
    {
        $this->group = $group;

        return $this;
    }

    /**
     * Return true if the user should do two-factor authentication.
     */
    public function isGoogleAuthenticatorEnabled(): bool
    {
        return $this->googleAuthenticatorSecret ? true : false;
    }

    /**
     * Return the user name that should be shown in Google Authenticator.
     */
    public function getGoogleAuthenticatorUsername(): string
    {
        return $this->getUsername();
    }

    /**
     * Return the Google Authenticator secret
     * When an empty string is returned, the Google authentication is disabled.
     */
    public function getGoogleAuthenticatorSecret(): ?string
    {
        return $this->googleAuthenticatorSecret;
    }

    /**
     * Sets the secret used for Google Authenticator. Set to null to disable Google Authenticator.
     *
     * @return $this
     */
    public function setGoogleAuthenticatorSecret(?string $googleAuthenticatorSecret): self
    {
        $this->googleAuthenticatorSecret = $googleAuthenticatorSecret;

        return $this;
    }

    /**
     * Check if the given code is a valid backup code.
     *
     * @param string $code the code that should be checked
     *
     * @return bool true if the backup code is valid
     */
    public function isBackupCode(string $code): bool
    {
        return in_array($code, $this->backupCodes, true);
    }

    /**
     * Invalidate a backup code.
     *
     * @param string $code The code that should be invalidated
     */
    public function invalidateBackupCode(string $code): void
    {
        $key = array_search($code, $this->backupCodes, true);
        if (false !== $key) {
            unset($this->backupCodes[$key]);
        }
    }

    /**
     * Returns the list of all valid backup codes.
     *
     * @return string[] An array with all backup codes
     */
    public function getBackupCodes(): array
    {
        return $this->backupCodes ?? [];
    }

    /**
     * Set the backup codes for this user. Existing backup codes are overridden.
     *
     * @param string[] $codes An array containing the backup codes
     *
     * @return $this
     *
     * @throws Exception If an error with the datetime occurs
     */
    public function setBackupCodes(array $codes): self
    {
        $this->backupCodes = $codes;
        if (empty($codes)) {
            $this->backupCodesGenerationDate = null;
        } else {
            $this->backupCodesGenerationDate = new DateTime();
        }

        return $this;
    }

    /**
     * Return the date when the backup codes were generated.
     */
    public function getBackupCodesGenerationDate(): ?DateTime
    {
        return $this->backupCodesGenerationDate;
    }

    /**
     * Return version for the trusted device token. Increase version to invalidate all trusted token of the user.
     *
     * @return int The version of trusted device token
     */
    public function getTrustedTokenVersion(): int
    {
        return $this->trustedDeviceCookieVersion;
    }

    /**
     * Invalidate all trusted device tokens at once, by incrementing the token version.
     * You have to flush the changes to database afterwards.
     */
    public function invalidateTrustedDeviceTokens(): void
    {
        ++$this->trustedDeviceCookieVersion;
    }

    /**
     * Check if U2F is enabled.
     */
    public function isU2FAuthEnabled(): bool
    {
        return count($this->u2fKeys) > 0;
    }

    /**
     *  Get all U2F Keys that are associated with this user.
     *
     * @psalm-return Collection<int, TwoFactorKeyInterface>
     */
    public function getU2FKeys(): Collection
    {
        return $this->u2fKeys;
    }

    /**
     * Add a U2F key to this user.
     */
    public function addU2FKey(TwoFactorKeyInterface $key): void
    {
        $this->u2fKeys->add($key);
    }

    /**
     * Remove a U2F key from this user.
     */
    public function removeU2FKey(TwoFactorKeyInterface $key): void
    {
        $this->u2fKeys->removeElement($key);
    }

    public function getPreferredTwoFactorProvider(): ?string
    {
        //If U2F is available then prefer it
        if ($this->isU2FAuthEnabled()) {
            return 'u2f_two_factor';
        }

        //Otherwise use other methods
        return null;
    }
}
