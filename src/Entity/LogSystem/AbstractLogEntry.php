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

namespace App\Entity\LogSystem;

use Doctrine\DBAL\Types\Types;
use App\Entity\Base\AbstractDBElement;
use App\Entity\UserSystem\User;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\LogEntryRepository;

/**
 * This entity describes an entry in the event log.
 * @see \App\Tests\Entity\LogSystem\AbstractLogEntryTest
 */
#[ORM\Entity(repositoryClass: LogEntryRepository::class)]
#[ORM\Table('log')]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'smallint')]
#[ORM\DiscriminatorMap([1 => 'UserLoginLogEntry', 2 => 'UserLogoutLogEntry', 3 => 'UserNotAllowedLogEntry', 4 => 'ExceptionLogEntry', 5 => 'ElementDeletedLogEntry', 6 => 'ElementCreatedLogEntry', 7 => 'ElementEditedLogEntry', 8 => 'ConfigChangedLogEntry', 9 => 'LegacyInstockChangedLogEntry', 10 => 'DatabaseUpdatedLogEntry', 11 => 'CollectionElementDeleted', 12 => 'SecurityEventLogEntry', 13 => 'PartStockChangedLogEntry'])]
#[ORM\Index(columns: ['type'], name: 'log_idx_type')]
#[ORM\Index(columns: ['type', 'target_type', 'target_id'], name: 'log_idx_type_target')]
#[ORM\Index(columns: ['datetime'], name: 'log_idx_datetime')]
abstract class AbstractLogEntry extends AbstractDBElement
{
    /** @var User|null The user which has caused this log entry
     */
    #[ORM\ManyToOne(targetEntity: User::class, fetch: 'EAGER')]
    #[ORM\JoinColumn(name: 'id_user', onDelete: 'SET NULL')]
    protected ?User $user = null;

    /**
     * @var string The username of the user which has caused this log entry (shown if the user is deleted)
     */
    #[ORM\Column(type: Types::STRING)]
    protected string $username = '';

    /**
     * @var \DateTimeImmutable The datetime the event associated with this log entry has occured
     */
    #[ORM\Column(name: 'datetime', type: Types::DATETIME_IMMUTABLE)]
    protected \DateTimeImmutable $timestamp;

    /**
     * @var LogLevel The priority level of the associated level. 0 is highest, 7 lowest
     */
    #[ORM\Column(name: 'level', type: 'tinyint', enumType: LogLevel::class)]
    protected LogLevel $level = LogLevel::WARNING;

    /** @var int The ID of the element targeted by this event
     */
    #[ORM\Column(name: 'target_id', type: Types::INTEGER)]
    protected int $target_id = 0;

    /** @var LogTargetType The Type of the targeted element
     */
    #[ORM\Column(name: 'target_type', type: Types::SMALLINT, enumType: LogTargetType::class)]
    protected LogTargetType $target_type = LogTargetType::NONE;

    /** @var string The type of this log entry, aka the description what has happened.
     * The mapping between the log entry class and the discriminator column is done by doctrine.
     * Each subclass should override this string to specify a better string.
     */
    protected string $typeString = 'unknown';

    /** @var array The extra data in raw (short form) saved in the DB
     */
    #[ORM\Column(name: 'extra', type: Types::JSON)]
    protected array $extra = [];

    public function __construct()
    {
        $this->timestamp = new \DateTimeImmutable();
    }

    /**
     * Get the user that caused the event associated with this log entry.
     *
     * @return User
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * Sets the user that caused the event.
     *
     * @return $this
     */
    public function setUser(User $user): self
    {
        $this->user = $user;

        //Save the username for later use
        $this->username = $user->getUsername();

        return $this;
    }

    /**
     * Returns true if this log entry was created by a CLI command, false otherwise.
     * @return bool
     */
    public function isCLIEntry(): bool
    {
        return str_starts_with($this->username, '!!!CLI ');
    }

    /**
     * Marks this log entry as a CLI entry, and set the username of the CLI user.
     * This removes the association to a user object in database, as CLI users are not really related to logged in
     * Part-DB users.
     * @return $this
     */
    public function setCLIUsername(string $cli_username): self
    {
        $this->user = null;
        $this->username = '!!!CLI ' . $cli_username;
        return $this;
    }

    /**
     * Retrieves the username of the CLI user that caused the event.
     * @return string|null The username of the CLI user, or null if this log entry was not created by a CLI command.
     */
    public function getCLIUsername(): ?string
    {
        if ($this->isCLIEntry()) {
            return substr($this->username, 7);
        }
        return null;
    }

    /**
     * Retuns the username of the user that caused the event (useful if the user was deleted).
     *
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     *  Returns the timestamp when the event that caused this log entry happened.
     */
    public function getTimestamp(): \DateTimeImmutable
    {
        return $this->timestamp;
    }

    /**
     * Sets the timestamp when the event happened.
     *
     * @return $this
     */
    public function setTimestamp(\DateTimeImmutable $timestamp): self
    {
        $this->timestamp = $timestamp;

        return $this;
    }

    /**
     * Get the priority level of this log entry.
     */
    public function getLevel(): LogLevel
    {
        return $this->level;
    }

    /**
     * Sets the new level of this log entry.
     *
     * @return $this
     */
    public function setLevel(LogLevel $level): self
    {
        $this->level = $level;
        return $this;
    }

    /**
     * Get the priority level of this log entry as PSR3 compatible string.
     */
    public function getLevelString(): string
    {
        return $this->level->toPSR3LevelString();
    }

    /**
     * Sets the priority level of this log entry as PSR3 compatible string.
     *
     * @return $this
     */
    public function setLevelString(string $level): self
    {
        LogLevel::fromPSR3LevelString($level);
        return $this;
    }

    /**
     * Returns the type of the event this log entry is associated with.
     */
    public function getType(): string
    {
        return $this->typeString;
    }

    /**
     * Returns the class name of the target element associated with this log entry.
     * Returns null, if this log entry is not associated with a log entry.
     *
     * @return string|null the class name of the target class
     */
    public function getTargetClass(): ?string
    {
        return $this->target_type->toClass();
    }

    /**
     * Returns the type of the target element associated with this log entry.
     * @return LogTargetType
     */
    public function getTargetType(): LogTargetType
    {
        return $this->target_type;
    }

    /**
     * Returns the ID of the target element associated with this log entry.
     * Returns null, if this log entry is not associated with a log entry.
     *
     * @return int|null the ID of the associated element
     */
    public function getTargetID(): ?int
    {
        if (0 === $this->target_id) {
            return null;
        }

        return $this->target_id;
    }

    /**
     * Checks if this log entry is associated with an element.
     *
     * @return bool true if this log entry is associated with an element, false otherwise
     */
    public function hasTarget(): bool
    {
        return null !== $this->getTargetID() && null !== $this->getTargetClass();
    }

    /**
     * Sets the target element associated with this element.
     *
     * @param  AbstractDBElement|null  $element  the element that should be associated with this element
     *
     * @return $this
     */
    public function setTargetElement(?AbstractDBElement $element): self
    {
        if ($element === null) {
            $this->target_id = 0;
            $this->target_type = LogTargetType::NONE;

            return $this;
        }

        $this->target_type = LogTargetType::fromElementClass($element);
        $this->target_id = $element->getID();

        return $this;
    }

    /**
     * Sets the target ID of the element associated with this element.
     *
     * @return $this
     */
    public function setTargetElementID(int $target_id): self
    {
        $this->target_id = $target_id;

        return $this;
    }

    public function getExtraData(): array
    {
        return $this->extra;
    }

}
