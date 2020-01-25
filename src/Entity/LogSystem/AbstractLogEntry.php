<?php
declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Entity\LogSystem;

use App\Entity\Attachments\Attachment;
use App\Entity\Base\DBElement;
use App\Entity\Devices\Device;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\Part;
use App\Entity\Parts\Storelocation;
use App\Entity\Parts\Supplier;
use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\User;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Attachments\AttachmentType;
use App\Entity\Devices\DevicePart;
use Psr\Log\LogLevel;

/**
 * This entity describes a entry in the event log.
 * @package App\Entity\LogSystem
 * @ORM\Entity(repositoryClass="App\Repository\LogEntryRepository")
 * @ORM\Table("log")
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="type", type="smallint")
 * @ORM\DiscriminatorMap({
 *  1 = "UserLoginLogEntry",
 *  2 = "UserLogoutLogEntry",
 *  3 = "UserNotAllowedLogEntry",
 *  4 = "ExceptionLogEntry",
 *  5 = "ElementDeletedLogEntry",
 *  6 = "ElementCreatedLogEntry",
 *  7 = "ElementEditedLogEntry",
 *  8 = "ConfigChangedLogEntry",
 *  9 = "InstockChangedLogEntry",
 *  10 = "DatabaseUpdatedLogEntry"
 * })
 */
abstract class AbstractLogEntry extends DBElement
{
    public const LEVEL_EMERGENCY = 0;
    public const LEVEL_ALERT = 1;
    public const LEVEL_CRITICAL = 2;
    public const LEVEL_ERROR = 3;
    public const LEVEL_WARNING = 4;
    public const LEVEL_NOTICE = 5;
    public const LEVEL_INFO = 6;
    public const LEVEL_DEBUG = 7;

    protected const TARGET_TYPE_NONE = 0;
    protected const TARGET_TYPE_USER = 1;
    protected const TARGET_TYPE_ATTACHEMENT = 2;
    protected const TARGET_TYPE_ATTACHEMENTTYPE = 3;
    protected const TARGET_TYPE_CATEGORY = 4;
    protected const TARGET_TYPE_DEVICE = 5;
    protected const TARGET_TYPE_DEVICEPART = 6;
    protected const TARGET_TYPE_FOOTPRINT = 7;
    protected const TARGET_TYPE_GROUP = 8;
    protected const TARGET_TYPE_MANUFACTURER = 9;
    protected const TARGET_TYPE_PART = 10;
    protected const TARGET_TYPE_STORELOCATION = 11;
    protected const TARGET_TYPE_SUPPLIER = 12;

    /** @var array This const is used to convert the numeric level to a PSR-3 compatible log level */
    protected const LEVEL_ID_TO_STRING = [
        self::LEVEL_EMERGENCY => LogLevel::EMERGENCY,
        self::LEVEL_ALERT => LogLevel::ALERT,
        self::LEVEL_CRITICAL => LogLevel::CRITICAL,
        self::LEVEL_ERROR => LogLevel::ERROR,
        self::LEVEL_WARNING => LogLevel::WARNING,
        self::LEVEL_NOTICE => LogLevel::NOTICE,
        self::LEVEL_INFO => LogLevel::INFO,
        self::LEVEL_DEBUG => LogLevel::DEBUG,
    ];


    protected const TARGET_CLASS_MAPPING = [
        self::TARGET_TYPE_USER => User::class,
        self::TARGET_TYPE_ATTACHEMENT => Attachment::class,
        self::TARGET_TYPE_ATTACHEMENTTYPE => AttachmentType::class,
        self::TARGET_TYPE_CATEGORY => Category::class,
        self::TARGET_TYPE_DEVICE => Device::class,
        self::TARGET_TYPE_DEVICEPART => DevicePart::class,
        self::TARGET_TYPE_FOOTPRINT => Footprint::class,
        self::TARGET_TYPE_GROUP => Group::class,
        self::TARGET_TYPE_MANUFACTURER => Manufacturer::class,
        self::TARGET_TYPE_PART => Part::class,
        self::TARGET_TYPE_STORELOCATION => Storelocation::class,
        self::TARGET_TYPE_SUPPLIER => Supplier::class,
    ];

    /** @var User $user The user which has caused this log entry
     * @ORM\ManyToOne(targetEntity="App\Entity\UserSystem\User")
     * @ORM\JoinColumn(name="id_user")
     */
    protected $user;

    /** @var DateTime The datetime the event associated with this log entry has occured.
     * @ORM\Column(type="datetime", name="datetime")
     */
    protected $timestamp;

    /** @var integer The priority level of the associated level. 0 is highest, 7 lowest
     * @ORM\Column(type="integer", name="level", columnDefinition="TINYINT")
     */
    protected $level;

    /** @var int $target_id The ID of the element targeted by this event
     * @ORM\Column(name="target_id", type="integer", nullable=false)
     */
    protected $target_id = 0;

    /** @var int $target_type The Type of the targeted element
     * @ORM\Column(name="target_type", type="smallint", nullable=false)
     */
    protected $target_type = 0;

    /** @var string The type of this log entry, aka the description what has happened.
     * The mapping between the log entry class and the discriminator column is done by doctrine.
     * Each subclass should override this string to specify a better string.
     */
    protected $typeString = "unknown";

    /** @var array The extra data in raw (short form) saved in the DB
     * @ORM\Column(name="extra", type="json")
     */
    protected $extra = [];

    /**
     * Get the user that caused the event associated with this log entry.
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * Sets the user that caused the event.
     * @param  User  $user
     * @return $this
     */
    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Returns the timestamp when the event that caused this log entry happened
     * @return DateTime
     */
    public function getTimestamp(): DateTime
    {
        return $this->timestamp;
    }

    /**
     * Sets the timestamp when the event happened.
     * @param  DateTime  $timestamp
     * @return $this
     */
    public function setTimestamp(DateTime $timestamp): AbstractLogEntry
    {
        $this->timestamp = $timestamp;
        return $this;
    }

    /**
     * Get the priority level of this log entry. 0 is highest and 7 lowest level.
     * See LEVEL_* consts in this class for more info
     * @return int
     */
    public function getLevel(): int
    {
        //It is always alerting when a wrong int is saved in DB...
        if ($this->level < 0 || $this->level > 7) {
            return self::LEVEL_ALERT;
        }
        return $this->level;
    }

    /**
     * Sets the new level of this log entry.
     * @param  int  $level
     * @return $this
     */
    public function setLevel(int $level): AbstractLogEntry
    {
        if ($level < 0 || $this->level > 7) {
            throw new \InvalidArgumentException(sprintf('$level must be between 0 and 7! %d given!', $level));
        }
        $this->level = $level;
        return $this;
    }

    /**
     * Get the priority level of this log entry as PSR3 compatible string
     * @return string
     */
    public function getLevelString(): string
    {
        return self::levelIntToString($this->getLevel());
    }

    /**
     * Sets the priority level of this log entry as PSR3 compatible string
     * @param string $level
     * @return $this
     */
    public function setLevelString(string $level): AbstractLogEntry
    {
        $this->setLevel(self::levelStringToInt($level));
        return $this;
    }

    /**
     * Returns the type of the event this log entry is associated with.
     * @return string
     */
    public function getType(): string
    {
        return $this->typeString;
    }

    /**
     * @inheritDoc
     */
    public function getIDString(): string
    {
        return "LOG".$this->getID();
    }

    /**
     * Returns the class name of the target element associated with this log entry.
     * Returns null, if this log entry is not associated with an log entry.
     * @return string|null The class name of the target class.
     */
    public function getTargetClass(): ?string
    {
        if ($this->target_type === self::TARGET_TYPE_NONE) {
            return null;
        }

        return self::targetTypeIdToClass($this->target_type);
    }

    /**
     * Returns the ID of the target element associated with this log entry.
     * Returns null, if this log entry is not associated with an log entry.
     * @return int|null The ID of the associated element.
     */
    public function getTargetID(): ?int
    {
        if ($this->target_id === 0) {
            return null;
        }

        return $this->target_id;
    }

    /**
     * Checks if this log entry is associated with an element
     * @return bool True if this log entry is associated with an element, false otherwise.
     */
    public function hasTarget(): bool
    {
        return $this->getTargetID() !== null && $this->getTargetClass() !== null;
    }

    /**
     * Sets the target element associated with this element
     * @param  DBElement  $element The element that should be associated with this element.
     * @return $this
     */
    public function setTargetElement(?DBElement $element): self
    {
        if ($element === null) {
            $this->target_id = 0;
            $this->target_type = self::TARGET_TYPE_NONE;
            return $this;
        }

        $this->target_type = static::targetTypeClassToID(get_class($element));
        $this->target_id = $element->getID();

        return $this;
    }

    public function getExtraData(): array
    {
        return $this->extra;
    }

    /**
     * This function converts the internal numeric log level into an PSR3 compatible level string.
     * @param  int  $level The numerical log level
     * @return string The PSR3 compatible level string
     */
    final public static function levelIntToString(int $level): string
    {
        if (!isset(self::LEVEL_ID_TO_STRING[$level])) {
            throw new \InvalidArgumentException('No level with this int is existing!');
        }

        return self::LEVEL_ID_TO_STRING[$level];
    }

    /**
     * This function converts a PSR3 compatible string to the internal numeric level string.
     * @param string $level the PSR3 compatible string that should be converted
     * @return int The internal int representation.
     */
    final public static function levelStringToInt(string $level): int
    {
        $tmp = array_flip(self::LEVEL_ID_TO_STRING);
        if (!isset($tmp[$level])) {
            throw new \InvalidArgumentException('No level with this string is existing!');
        }

        return $tmp[$level];
    }

    /**
     * Converts an target type id to an full qualified class name.
     * @param  int  $type_id The target type ID
     * @return string
     */
    final public static function targetTypeIdToClass(int $type_id): string
    {
        if (!isset(self::TARGET_CLASS_MAPPING[$type_id])) {
            throw new \InvalidArgumentException('No target type with this ID is existing!');
        }

        return self::TARGET_CLASS_MAPPING[$type_id];
    }

    /**
     * Convert a class name to a target type ID.
     * @param  string  $class The name of the class (FQN) that should be converted to id
     * @return int The ID of the associated target type ID.
     */
    final public static function targetTypeClassToID(string $class): int
    {
        $tmp = array_flip(self::TARGET_CLASS_MAPPING);
        //Check if we can use a key directly
        if (isset($tmp[$class])) {
            return $tmp[$class];
        }

        //Otherwise we have to iterate over everything and check for inheritance
        foreach ($tmp as $compare_class => $class_id) {
            if (is_a($class, $compare_class, true)) {
                return $class_id;
            }
        }

        throw new \InvalidArgumentException('No target ID for this class is existing!');
    }


}