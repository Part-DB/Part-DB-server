<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Doctrine\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\SerializationFailed;
use Doctrine\DBAL\Types\Type;
use Doctrine\Deprecations\Deprecation;

use function is_resource;
use function restore_error_handler;
use function serialize;
use function set_error_handler;
use function stream_get_contents;
use function unserialize;

use const E_DEPRECATED;
use const E_USER_DEPRECATED;

/**
 * This class is taken from doctrine ORM 3.8. https://github.com/doctrine/dbal/blob/3.8.x/src/Types/ArrayType.php
 *
 * It was removed in doctrine ORM 4.0. However, we require it for backward compatibility with WebauthnKey.
 * Therefore, we manually added it here as a custom type as a forward compatibility layer.
 */
class ArrayType extends Type
{
    /**
     * {@inheritDoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getClobTypeDeclarationSQL($column);
    }

    /**
     * {@inheritDoc}
     */
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): string
    {
        return serialize($value);
    }

    /**
     * {@inheritDoc}
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if ($value === null) {
            return null;
        }

        $value = is_resource($value) ? stream_get_contents($value) : $value;

        set_error_handler(function (int $code, string $message): bool {
            if ($code === E_DEPRECATED || $code === E_USER_DEPRECATED) {
                return false;
            }

            //Change to original code. Use SerializationFailed instead of ConversionException.
            throw new SerializationFailed("Serialization failed (Code $code): " . $message);
        });

        try {
            //Change to original code. Use false for allowed_classes, to avoid unsafe unserialization of objects.
            return unserialize($value, ['allowed_classes' => false]);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return "array";
    }

    /**
     * {@inheritDoc}
     *
     * @deprecated
     */
    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5509',
            '%s is deprecated.',
            __METHOD__,
        );

        return true;
    }
}