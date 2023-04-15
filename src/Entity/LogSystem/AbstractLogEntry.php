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

use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\AttachmentType;
use App\Entity\Base\AbstractDBElement;
use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Entity\LabelSystem\LabelProfile;
use App\Entity\Parameters\AbstractParameter;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\Storelocation;
use App\Entity\Parts\Supplier;
use App\Entity\PriceInformations\Currency;
use App\Entity\PriceInformations\Orderdetail;
use App\Entity\PriceInformations\Pricedetail;
use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\User;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Psr\Log\LogLevel;

/**
 * This entity describes a entry in the event log.
 *
 * @ORM\Entity(repositoryClass="App\Repository\LogEntryRepository")
 * @ORM\Table("log", indexes={
 *    @ORM\Index(name="log_idx_type", columns={"type"}),
 *    @ORM\Index(name="log_idx_type_target", columns={"type", "target_type", "target_id"}),
 *    @ORM\Index(name="log_idx_datetime", columns={"datetime"}),
 * })
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
 *  9 = "LegacyInstockChangedLogEntry",
 *  10 = "DatabaseUpdatedLogEntry",
 *  11 = "CollectionElementDeleted",
 *  12 = "SecurityEventLogEntry",
 *  13 = "PartStockChangedLogEntry",
 * })
 */
abstract class AbstractLogEntry extends AbstractDBElement
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
    protected const TARGET_TYPE_PARTLOT = 13;
    protected const TARGET_TYPE_CURRENCY = 14;
    protected const TARGET_TYPE_ORDERDETAIL = 15;
    protected const TARGET_TYPE_PRICEDETAIL = 16;
    protected const TARGET_TYPE_MEASUREMENTUNIT = 17;
    protected const TARGET_TYPE_PARAMETER = 18;
    protected const TARGET_TYPE_LABEL_PROFILE = 19;

    /**
     * @var array This const is used to convert the numeric level to a PSR-3 compatible log level
     */
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
        self::TARGET_TYPE_DEVICE => Project::class,
        self::TARGET_TYPE_DEVICEPART => ProjectBOMEntry::class,
        self::TARGET_TYPE_FOOTPRINT => Footprint::class,
        self::TARGET_TYPE_GROUP => Group::class,
        self::TARGET_TYPE_MANUFACTURER => Manufacturer::class,
        self::TARGET_TYPE_PART => Part::class,
        self::TARGET_TYPE_STORELOCATION => Storelocation::class,
        self::TARGET_TYPE_SUPPLIER => Supplier::class,
        self::TARGET_TYPE_PARTLOT => PartLot::class,
        self::TARGET_TYPE_CURRENCY => Currency::class,
        self::TARGET_TYPE_ORDERDETAIL => Orderdetail::class,
        self::TARGET_TYPE_PRICEDETAIL => Pricedetail::class,
        self::TARGET_TYPE_MEASUREMENTUNIT => MeasurementUnit::class,
        self::TARGET_TYPE_PARAMETER => AbstractParameter::class,
        self::TARGET_TYPE_LABEL_PROFILE => LabelProfile::class,
    ];

    /** @var User|null The user which has caused this log entry
     * @ORM\ManyToOne(targetEntity="App\Entity\UserSystem\User", fetch="EAGER")
     * @ORM\JoinColumn(name="id_user", nullable=true, onDelete="SET NULL")
     */
    protected ?User $user = null;

    /**
     * @var string The username of the user which has caused this log entry (shown if the user is deleted)
     * @ORM\Column(type="string", nullable=false)
     */
    protected string $username = '';

    /** @var DateTime The datetime the event associated with this log entry has occured
     * @ORM\Column(type="datetime", name="datetime")
     */
    protected ?DateTime $timestamp = null;

    /** @var int The priority level of the associated level. 0 is highest, 7 lowest
     * @ORM\Column(type="tinyint", name="level", nullable=false)
     */
    protected int $level;

    /** @var int The ID of the element targeted by this event
     * @ORM\Column(name="target_id", type="integer", nullable=false)
     */
    protected int $target_id = 0;

    /** @var int The Type of the targeted element
     * @ORM\Column(name="target_type", type="smallint", nullable=false)
     */
    protected int $target_type = 0;

    /** @var string The type of this log entry, aka the description what has happened.
     * The mapping between the log entry class and the discriminator column is done by doctrine.
     * Each subclass should override this string to specify a better string.
     */
    protected string $typeString = 'unknown';

    /** @var array The extra data in raw (short form) saved in the DB
     * @ORM\Column(name="extra", type="json")
     */
    protected $extra = [];

    public function __construct()
    {
        $this->timestamp = new DateTime();
        $this->level = self::LEVEL_WARNING;
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
        return strpos($this->username, '!!!CLI ') === 0;
    }

    /**
     * Marks this log entry as a CLI entry, and set the username of the CLI user.
     * This removes the association to a user object in database, as CLI users are not really related to logged in
     * Part-DB users.
     * @param  string  $cli_username
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
     * Returns the timestamp when the event that caused this log entry happened.
     */
    public function getTimestamp(): DateTime
    {
        return $this->timestamp;
    }

    /**
     * Sets the timestamp when the event happened.
     *
     * @return $this
     */
    public function setTimestamp(DateTime $timestamp): self
    {
        $this->timestamp = $timestamp;

        return $this;
    }

    /**
     * Get the priority level of this log entry. 0 is highest and 7 lowest level.
     * See LEVEL_* consts in this class for more info.
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
     *
     * @return $this
     */
    public function setLevel(int $level): self
    {
        if ($level < 0 || $this->level > 7) {
            throw new InvalidArgumentException(sprintf('$level must be between 0 and 7! %d given!', $level));
        }
        $this->level = $level;

        return $this;
    }

    /**
     * Get the priority level of this log entry as PSR3 compatible string.
     */
    public function getLevelString(): string
    {
        return self::levelIntToString($this->getLevel());
    }

    /**
     * Sets the priority level of this log entry as PSR3 compatible string.
     *
     * @return $this
     */
    public function setLevelString(string $level): self
    {
        $this->setLevel(self::levelStringToInt($level));

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
     * Returns null, if this log entry is not associated with an log entry.
     *
     * @return string|null the class name of the target class
     */
    public function getTargetClass(): ?string
    {
        if (self::TARGET_TYPE_NONE === $this->target_type) {
            return null;
        }

        return self::targetTypeIdToClass($this->target_type);
    }

    /**
     * Returns the ID of the target element associated with this log entry.
     * Returns null, if this log entry is not associated with an log entry.
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
        if (null === $element) {
            $this->target_id = 0;
            $this->target_type = self::TARGET_TYPE_NONE;

            return $this;
        }

        $this->target_type = static::targetTypeClassToID(get_class($element));
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

    /**
     * This function converts the internal numeric log level into an PSR3 compatible level string.
     *
     * @param int $level The numerical log level
     *
     * @return string The PSR3 compatible level string
     */
    final public static function levelIntToString(int $level): string
    {
        if (!isset(self::LEVEL_ID_TO_STRING[$level])) {
            throw new InvalidArgumentException('No level with this int is existing!');
        }

        return self::LEVEL_ID_TO_STRING[$level];
    }

    /**
     * This function converts a PSR3 compatible string to the internal numeric level string.
     *
     * @param string $level the PSR3 compatible string that should be converted
     *
     * @return int the internal int representation
     */
    final public static function levelStringToInt(string $level): int
    {
        $tmp = array_flip(self::LEVEL_ID_TO_STRING);
        if (!isset($tmp[$level])) {
            throw new InvalidArgumentException('No level with this string is existing!');
        }

        return $tmp[$level];
    }

    /**
     * Converts an target type id to an full qualified class name.
     *
     * @param int $type_id The target type ID
     */
    final public static function targetTypeIdToClass(int $type_id): string
    {
        if (!isset(self::TARGET_CLASS_MAPPING[$type_id])) {
            throw new InvalidArgumentException('No target type with this ID is existing!');
        }

        return self::TARGET_CLASS_MAPPING[$type_id];
    }

    /**
     * Convert a class name to a target type ID.
     *
     * @param string $class The name of the class (FQN) that should be converted to id
     *
     * @return int the ID of the associated target type ID
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

        throw new InvalidArgumentException('No target ID for this class is existing! (Class: '.$class.')');
    }
}
