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

namespace App\Serializer\APIPlatform;

use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Exception\ItemNotFoundException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

/**
 * Shared denormalize()/supportsDenormalization() implementation for AbstractResourceIriNormalizer and
 * AbstractResourceIriDenormalizer. The using class must provide an $inner (de)normalizer, an
 * IriConverterInterface $iriConverter property and a ResourceClassResolverInterface $resourceClassResolver
 * property.
 *
 * It works around a bug in API Platform's AbstractItemNormalizer where IRI strings for abstract resource classes
 * with a discriminator map fail deserialization when objectToPopulate is null (the discriminator is checked before
 * the IRI string check). See: https://github.com/Part-DB/Part-DB-server/issues/1370
 */
trait AbstractResourceIriDenormalizationTrait
{
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        // API Platform's AbstractItemNormalizer has a bug: when objectToPopulate is null and data is an IRI
        // string, it tries to resolve the discriminator class from [$iri_string] before reaching the IRI
        // check (line 271). For abstract resource classes with a discriminator map (e.g. Attachment), this
        // fails because the array has no _type key. Fix by resolving IRI strings directly.
        // See: https://github.com/Part-DB/Part-DB-server/issues/1370
        //
        // $type must actually be an API resource for this to make sense: this normalizer also runs for plain
        // value objects (e.g. backed enums) nested inside a resource, and a string value there is the enum's
        // scalar value, not an IRI - treating it as one silently swallows the value instead of letting the
        // regular (enum) normalizer handle it.
        if ($this->resourceClassResolver->isResourceClass($type)
            && (is_string($data) || (is_array($data) && isset($data['@id']) && is_string($data['@id'])))) {
            $iri = is_array($data) ? $data['@id'] : $data;

            try {
                return $this->iriConverter->getResourceFromIri($iri, $context + ['fetch_data' => true]);
            } catch (ItemNotFoundException $e) {
                if (false === ($context['denormalize_throw_on_relation_not_found'] ?? true)) {
                    return null;
                }
                if (!isset($context['not_normalizable_value_exceptions'])) {
                    throw new UnexpectedValueException($e->getMessage(), $e->getCode(), $e);
                }
                throw NotNormalizableValueException::createForUnexpectedDataType($e->getMessage(), $data, [$type], $context['deserialization_path'] ?? null, true, $e->getCode(), $e);
            } catch (InvalidArgumentException $e) {
                if (!isset($context['not_normalizable_value_exceptions'])) {
                    throw new UnexpectedValueException(sprintf('Invalid IRI "%s".', $iri), $e->getCode(), $e);
                }
                throw NotNormalizableValueException::createForUnexpectedDataType(sprintf('Invalid IRI "%s".', $data), $data, [$type], $context['deserialization_path'] ?? null, true, $e->getCode(), $e);
            }
        }

        return $this->inner->denormalize($data, $type, $format, $context);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $this->inner->supportsDenormalization($data, $type, $format, $context);
    }
}
