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

use App\Repository\UserRepository;
use App\EntityListeners\TreeCacheInvalidationListener;
use Doctrine\DBAL\Types\Types;
use App\Entity\Attachments\AttachmentContainingDBElement;
use App\Entity\Attachments\UserAttachment;
use App\Entity\Base\AbstractNamedDBElement;
use App\Entity\PriceInformations\Currency;
use App\Security\Interfaces\HasPermissionsInterface;
use App\Validator\Constraints\Selectable;
use App\Validator\Constraints\ValidPermission;
use App\Validator\Constraints\ValidTheme;
use Jbtronics\TFAWebauthn\Model\LegacyU2FKeyInterface;
use Nbgrp\OneloginSamlBundle\Security\User\SamlUserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Webauthn\PublicKeyCredentialUserEntity;
use function count;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use function in_array;
use Scheb\TwoFactorBundle\Model\BackupCodeInterface;
use Scheb\TwoFactorBundle\Model\Google\TwoFactorInterface;
use Scheb\TwoFactorBundle\Model\PreferredProviderInterface;
use Scheb\TwoFactorBundle\Model\TrustedDeviceInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Jbtronics\TFAWebauthn\Model\TwoFactorInterface as WebauthnTwoFactorInterface;

/**
 * This entity represents a user, which can log in and have permissions.
 * Also, this entity is able to save some information about the user, like the names, email-address and other info.
 * @see \App\Tests\Entity\UserSystem\UserTest
 */
#[UniqueEntity('name', message: 'validator.user.username_already_used')]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\EntityListeners([TreeCacheInvalidationListener::class])]
#[ORM\Table('`users`')]
#[ORM\Index(name: 'user_idx_username', columns: ['name'])]
class User extends AttachmentContainingDBElement implements UserInterface, HasPermissionsInterface, TwoFactorInterface,
                                                            BackupCodeInterface, TrustedDeviceInterface, WebauthnTwoFactorInterface, PreferredProviderInterface, PasswordAuthenticatedUserInterface, SamlUserInterface
{
    //use MasterAttachmentTrait;

    /**
     * The User id of the anonymous user.
     */
    final public const ID_ANONYMOUS = 1;

    /**
     * @var bool Determines if the user is disabled (user can not log in)
     */
    #[Groups(['extended', 'full', 'import'])]
    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $disabled = false;

    /**
     * @var string|null The theme
     * @ValidTheme()
     */
    #[Groups(['full', 'import'])]
    #[ORM\Column(type: Types::STRING, name: 'config_theme', nullable: true)]
    protected ?string $theme = null;

    /**
     * @var string|null the hash of a token the user must provide when he wants to reset his password
     */
    #[ORM\Column(type: Types::STRING, nullable: true)]
    protected ?string $pw_reset_token = null;

    #[ORM\Column(type: Types::TEXT, name: 'config_instock_comment_a')]
    protected string $instock_comment_a = '';

    #[ORM\Column(type: Types::TEXT, name: 'config_instock_comment_w')]
    protected string $instock_comment_w = '';

    /**
     * @var string A self-description of the user
     */
    #[Groups(['full', 'import'])]
    #[ORM\Column(type: Types::TEXT)]
    protected string $aboutMe = '';

    /** @var int The version of the trusted device cookie. Used to invalidate all trusted device cookies at once.
     */
    #[ORM\Column(type: Types::INTEGER)]
    protected int $trustedDeviceCookieVersion = 0;

    /**
     * @var string[]|null A list of backup codes that can be used, if the user has no access to its Google Authenticator device
     */
    #[ORM\Column(type: Types::JSON)]
    protected ?array $backupCodes = [];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    protected ?int $id = null;

    /**
     * @var Group|null the group this user belongs to
     * DO NOT PUT A fetch eager here! Otherwise, you can not unset the group of a user! This seems to be some kind of bug in doctrine. Maybe this is fixed in future versions.
     * @Selectable()
     */
    #[Groups(['extended', 'full', 'import'])]
    #[ORM\ManyToOne(targetEntity: 'Group', inversedBy: 'users')]
    #[ORM\JoinColumn(name: 'group_id')]
    protected ?Group $group = null;

    /**
     * @var string|null The secret used for Google authenticator
     */
    #[ORM\Column(name: 'google_authenticator_secret', type: Types::STRING, nullable: true)]
    protected ?string $googleAuthenticatorSecret = null;

    /**
     * @var string|null The timezone the user prefers
     */
    #[Assert\Timezone]
    #[Groups(['full', 'import'])]
    #[ORM\Column(type: Types::STRING, name: 'config_timezone', nullable: true)]
    protected ?string $timezone = '';

    /**
     * @var string|null The language/locale the user prefers
     */
    #[Assert\Language]
    #[Groups(['full', 'import'])]
    #[ORM\Column(type: Types::STRING, name: 'config_language', nullable: true)]
    protected ?string $language = '';

    /**
     * @var string|null The email address of the user
     */
    #[Assert\Email]
    #[Groups(['simple', 'extended', 'full', 'import'])]
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    protected ?string $email = '';

    /**
     * @var bool True if the user wants to show his email address on his (public) profile
     */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    protected bool $show_email_on_profile = false;

    /**
     * @var string|null The department the user is working
     */
    #[Groups(['simple', 'extended', 'full', 'import'])]
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    protected ?string $department = '';

    /**
     * @var string|null The last name of the User
     */
    #[Groups(['simple', 'extended', 'full', 'import'])]
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    protected ?string $last_name = '';

    /**
     * @var string|null The first name of the User
     */
    #[Groups(['simple', 'extended', 'full', 'import'])]
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    protected ?string $first_name = '';

    /**
     * @var bool True if the user needs to change password after log in
     */
    #[Groups(['extended', 'full', 'import'])]
    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $need_pw_change = true;

    /**
     * @var string|null The hashed password
     */
    #[ORM\Column(type: Types::STRING, nullable: true)]
    protected ?string $password = null;

    #[Assert\NotBlank]
    #[Assert\Regex('/^[\w\.\+\-\$]+$/', message: 'user.invalid_username')]
    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    protected string $name = '';

    /**
     * @var array|null
     */
    #[ORM\Column(type: Types::JSON)]
    protected ?array $settings = [];

    /**
     * @var Collection<int, UserAttachment>
     */
    #[ORM\OneToMany(targetEntity: UserAttachment::class, mappedBy: 'element', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['name' => 'ASC'])]
    protected Collection $attachments;

    /** @var \DateTimeInterface|null The time when the backup codes were generated
     */
    #[Groups(['full'])]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    protected ?\DateTimeInterface $backupCodesGenerationDate = null;

    /** @var Collection<int, LegacyU2FKeyInterface>
     */
    #[ORM\OneToMany(targetEntity: U2FKey::class, mappedBy: 'user', cascade: ['REMOVE'], orphanRemoval: true)]
    protected Collection $u2fKeys;

    /**
     * @var Collection<int, WebauthnKey>
     */
    #[ORM\OneToMany(targetEntity: WebauthnKey::class, mappedBy: 'user', cascade: ['REMOVE'], orphanRemoval: true)]
    protected Collection $webauthn_keys;

    /**
     * @var Currency|null The currency the user wants to see prices in.
     *                    Dont use fetch=EAGER here, this will cause problems with setting the currency setting.
     *                    TODO: This is most likely a bug in doctrine/symfony related to the UniqueEntity constraint (it makes a db call).
     *                    TODO: Find a way to use fetch EAGER (this improves performance a bit)
     * @Selectable()
     */
    #[Groups(['extended', 'full', 'import'])]
    #[ORM\ManyToOne(targetEntity: Currency::class)]
    #[ORM\JoinColumn(name: 'currency_id')]
    protected ?Currency $currency = null;

    /**
     * @var PermissionData|null
     * @ValidPermission()
     */
    #[Groups(['simple', 'extended', 'full', 'import'])]
    #[ORM\Embedded(class: 'PermissionData', columnPrefix: 'permissions_')]
    protected ?PermissionData $permissions = null;

    /**
     * @var \DateTimeInterface|null the time until the password reset token is valid
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    protected ?\DateTimeInterface $pw_reset_expires = null;

    /**
     * @var bool True if the user was created by a SAML provider (and therefore cannot change its password)
     */
    #[Groups(['extended', 'full'])]
    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $saml_user = false;

    public function __construct()
    {
        $this->attachments = new ArrayCollection();
        parent::__construct();
        $this->permissions = new PermissionData();
        $this->u2fKeys = new ArrayCollection();
        $this->webauthn_keys = new ArrayCollection();
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
        return $this->name;
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

        if ($this->saml_user) {
            $roles[] = 'ROLE_SAML_USER';
        }

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
     */
    public function setCurrency(?Currency $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * Checks if this user is disabled (user cannot log in any more).
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
     */
    public function setDisabled(bool $disabled): self
    {
        $this->disabled = $disabled;

        return $this;
    }

    public function getPermissions(): PermissionData
    {
        if (!$this->permissions instanceof PermissionData) {
            $this->permissions = new PermissionData();
        }

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
     */
    public function setPwResetToken(?string $pw_reset_token): self
    {
        $this->pw_reset_token = $pw_reset_token;

        return $this;
    }

    /**
     * Gets the datetime when the password reset token expires.
     */
    public function getPwResetExpires(): \DateTimeInterface
    {
        return $this->pw_reset_expires;
    }

    /**
     * Sets the datetime when the password reset token expires.
     */
    public function setPwResetExpires(\DateTimeInterface $pw_reset_expires): self
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
        //Don't add a space, if the name has only one part (it would look strange)
        if ($this->getFirstName() !== null && $this->getFirstName() !== '' && ($this->getLastName() !== null && $this->getLastName() !== '')) {
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
     * @param  string|null  $first_name  The new first name
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
     * @param  string|null  $last_name  The new last name
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
     * @param  string|null  $department  The new department
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
     * @param  string|null  $email  The new email adress
     *
     * @return $this
     */
    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Gets whether the email address of the user is shown on the public profile page.
     */
    public function isShowEmailOnProfile(): bool
    {
        return $this->show_email_on_profile;
    }

    /**
     * Sets whether the email address of the user is shown on the public profile page.
     */
    public function setShowEmailOnProfile(bool $show_email_on_profile): User
    {
        $this->show_email_on_profile = $show_email_on_profile;
        return $this;
    }



    /**
     * Returns the about me text of the user.
     */
    public function getAboutMe(): string
    {
        return $this->aboutMe;
    }

    /**
     * Change the about me text of the user.
     */
    public function setAboutMe(string $aboutMe): User
    {
        $this->aboutMe = $aboutMe;
        return $this;
    }



    /**
     * Gets the language the user prefers (as 2-letter ISO code).
     *
     * @return string|null The 2-letter ISO code of the preferred language (e.g. 'en' or 'de').
     *                     If null is returned, the user has not specified a language and the server wide language should be used.
     */
    public function getLanguage(): ?string
    {
        return $this->language;
    }

    /**
     * Change the language the user prefers.
     *
     * @param string|null $language The new language as 2-letter ISO code (e.g. 'en' or 'de').
     *                              Set to null, to use the system-wide language.
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
     * @param string|null $theme The name of the theme (See self::AVAILABLE_THEMES for valid values). Set to null
     *                           if the system-wide theme should be used.
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
        return (bool)$this->googleAuthenticatorSecret;
    }

    /**
     * Return the username that should be shown in Google Authenticator.
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
        return in_array($code, $this->getBackupCodes(), true);
    }

    /**
     * Invalidate a backup code.
     *
     * @param string $code The code that should be invalidated
     */
    public function invalidateBackupCode(string $code): void
    {
        $key = array_search($code, $this->getBackupCodes(), true);
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
        $this->backupCodesGenerationDate = $codes === [] ? null : new DateTime();

        return $this;
    }

    /**
     * Return the date when the backup codes were generated.
     */
    public function getBackupCodesGenerationDate(): ?\DateTimeInterface
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
     * You have to flush the changes to database afterward.
     */
    public function invalidateTrustedDeviceTokens(): void
    {
        ++$this->trustedDeviceCookieVersion;
    }

    public function getPreferredTwoFactorProvider(): ?string
    {
        //If U2F is available then prefer it
        //if ($this->isU2FAuthEnabled()) {
        //    return 'u2f_two_factor';
        //}

        if ($this->isWebAuthnAuthenticatorEnabled()) {
            return 'webauthn_two_factor_provider';
        }

        //Otherwise use other methods
        return null;
    }

    public function isWebAuthnAuthenticatorEnabled(): bool
    {
        return count($this->u2fKeys) > 0
            || count($this->webauthn_keys) > 0;
    }

    public function getLegacyU2FKeys(): iterable
    {
        return $this->u2fKeys;
    }

    public function getWebAuthnUser(): PublicKeyCredentialUserEntity
    {
        return new PublicKeyCredentialUserEntity(
            $this->getUsername(),
            (string) $this->getId(),
            $this->getFullName(),
        );
    }

    public function getWebauthnKeys(): iterable
    {
        return $this->webauthn_keys;
    }

    public function addWebauthnKey(WebauthnKey $webauthnKey): void
    {
        $this->webauthn_keys->add($webauthnKey);
    }

    /**
     * Returns true, if the user was created by the SAML authentication.
     */
    public function isSamlUser(): bool
    {
        return $this->saml_user;
    }

    /**
     * Sets the saml_user flag.
     */
    public function setSamlUser(bool $saml_user): User
    {
        $this->saml_user = $saml_user;
        return $this;
    }



    public function setSamlAttributes(array $attributes): void
    {
        //When mail attribute exists, set it
        if (isset($attributes['email'])) {
            $this->setEmail($attributes['email'][0]);
        }
        //When first name attribute exists, set it
        if (isset($attributes['firstName'])) {
            $this->setFirstName($attributes['firstName'][0]);
        }
        //When last name attribute exists, set it
        if (isset($attributes['lastName'])) {
            $this->setLastName($attributes['lastName'][0]);
        }
        if (isset($attributes['department'])) {
            $this->setDepartment($attributes['department'][0]);
        }

        //Use X500 attributes as userinfo
        if (isset($attributes['urn:oid:2.5.4.42'])) {
            $this->setFirstName($attributes['urn:oid:2.5.4.42'][0]);
        }
        if (isset($attributes['urn:oid:2.5.4.4'])) {
            $this->setLastName($attributes['urn:oid:2.5.4.4'][0]);
        }
        if (isset($attributes['urn:oid:1.2.840.113549.1.9.1'])) {
            $this->setEmail($attributes['urn:oid:1.2.840.113549.1.9.1'][0]);
        }
    }
}
