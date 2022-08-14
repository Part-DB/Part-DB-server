<?php

declare(strict_types=1);

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

namespace App\Entity\LogSystem;

use App\Entity\Base\AbstractDBElement;
use App\Entity\UserSystem\User;
use App\Events\SecurityEvents;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * This log entry is created when something security related to a user happens.
 *
 * @ORM\Entity()
 */
class SecurityEventLogEntry extends AbstractLogEntry
{
    public const SECURITY_TYPE_MAPPING = [
        0 => SecurityEvents::PASSWORD_CHANGED,
        1 => SecurityEvents::PASSWORD_RESET,
        2 => SecurityEvents::BACKUP_KEYS_RESET,
        3 => SecurityEvents::U2F_ADDED,
        4 => SecurityEvents::U2F_REMOVED,
        5 => SecurityEvents::GOOGLE_ENABLED,
        6 => SecurityEvents::GOOGLE_DISABLED,
        7 => SecurityEvents::TRUSTED_DEVICE_RESET,
        8 => SecurityEvents::TFA_ADMIN_RESET,
    ];

    public function __construct(string $type, string $ip_address, bool $anonymize = true)
    {
        parent::__construct();
        $this->level = self::LEVEL_INFO;
        $this->setIPAddress($ip_address, $anonymize);
        $this->setEventType($type);
        $this->level = self::LEVEL_NOTICE;
    }

    public function setTargetElement(?AbstractDBElement $element): AbstractLogEntry
    {
        if (!$element instanceof User) {
            throw new InvalidArgumentException('Target element must be a User object!');
        }

        return parent::setTargetElement($element);
    }

    /**
     * Sets the type of this log entry.
     *
     * @return $this
     */
    public function setEventType(string $type): self
    {
        $key = array_search($type, static::SECURITY_TYPE_MAPPING, true);
        if (false === $key) {
            throw new InvalidArgumentException('Given event type is not existing!');
        }
        $this->extra['e'] = $key;

        return $this;
    }

    public function getType(): string
    {
        return $this->getEventType();
    }

    /**
     * Return what event this log entry represents (e.g. password_reset).
     */
    public function getEventType(): string
    {
        $key = $this->extra['e'];

        return static::SECURITY_TYPE_MAPPING[$key] ?? 'unkown';
    }

    /**
     * Return the (anonymized) IP address used to login the user.
     */
    public function getIPAddress(): string
    {
        return $this->extra['i'];
    }

    /**
     * Sets the IP address used to login the user.
     *
     * @param string $ip        the IP address used to login the user
     * @param bool   $anonymize Anonymize the IP address (remove last block) to be GPDR compliant
     *
     * @return $this
     */
    public function setIPAddress(string $ip, bool $anonymize = true): self
    {
        if ($anonymize) {
            $ip = IpUtils::anonymize($ip);
        }
        $this->extra['i'] = $ip;

        return $this;
    }
}
