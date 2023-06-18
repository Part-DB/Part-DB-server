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

namespace App\Doctrine\Types;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\DateTimeType;

/**
 * This DateTimeType all dates to UTC, so it can be later used with the timezones.
 * Taken from here: https://www.doctrine-project.org/projects/doctrine-orm/en/2.6/cookbook/working-with-datetime.html.
 */
class UTCDateTimeType extends DateTimeType
{
    private static ?DateTimeZone $utc_timezone = null;

    /**
     * {@inheritdoc}
     *
     * @param T $value
     *
     * @return (T is null ? null : string)
     *
     * @template T
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (!self::$utc_timezone instanceof \DateTimeZone) {
            self::$utc_timezone = new DateTimeZone('UTC');
        }

        if ($value instanceof DateTime) {
            $value->setTimezone(self::$utc_timezone);
        }

        return parent::convertToDatabaseValue($value, $platform);
    }

    /**
     * {@inheritDoc}
     *
     * @param T $value
     *
     * @return (T is null ? null : DateTimeInterface)
     *
     * @template T
     */
    public function convertToPHPValue($value, AbstractPlatform $platform): ?\DateTimeInterface
    {
        if (!self::$utc_timezone instanceof \DateTimeZone) {
            self::$utc_timezone = new DateTimeZone('UTC');
        }

        if (null === $value || $value instanceof DateTime) {
            return $value;
        }

        $converted = DateTime::createFromFormat(
            $platform->getDateTimeFormatString(),
            $value,
            self::$utc_timezone
        );

        if (!$converted) {
            throw ConversionException::conversionFailedFormat($value, $this->getName(), $platform->getDateTimeFormatString());
        }

        return $converted;
    }
}
