<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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
namespace App\Serializer;

use App\Entity\Base\AbstractStructuralDBElement;
use App\Repository\StructuralDBElementRepository;
use App\Serializer\APIPlatform\SkippableItemNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

/**
 * @see \App\Tests\Serializer\StructuralElementDenormalizerTest
 */
class StructuralElementDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{

    use DenormalizerAwareTrait;

    private const ALREADY_CALLED = 'STRUCTURAL_DENORMALIZER_ALREADY_CALLED';

    private array $object_cache = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager)
    {
    }

    public function supportsDenormalization($data, string $type, ?string $format = null, array $context = []): bool
    {
        //Only denormalize if we are doing a file import operation
        if (!($context['partdb_import'] ?? false)) {
            return false;
        }

        //If we already handled this object, skip it
        if (isset($context[self::ALREADY_CALLED])
            && is_array($context[self::ALREADY_CALLED])
            && in_array($data, $context[self::ALREADY_CALLED], true)) {
            return false;
        }

        return is_array($data)
            && is_subclass_of($type, AbstractStructuralDBElement::class)
            //Only denormalize if we are doing a file import operation
            && in_array('import', $context['groups'] ?? [], true);
    }

    /**
     * @template T of AbstractStructuralDBElement
     * @param $data
     * @phpstan-param class-string<T> $type
     * @param string|null $format
     * @param array $context
     * @return AbstractStructuralDBElement|null
     * @phpstan-return T|null
     */
    public function denormalize($data, string $type, ?string $format = null, array $context = []): ?AbstractStructuralDBElement
    {
        //Do not use API Platform's denormalizer
        $context[SkippableItemNormalizer::DISABLE_ITEM_NORMALIZER] = true;

        if (!isset($context[self::ALREADY_CALLED])) {
            $context[self::ALREADY_CALLED] = [];
        }

        $context[self::ALREADY_CALLED][] = $data;


        /** @var AbstractStructuralDBElement $deserialized_entity */
        $deserialized_entity = $this->denormalizer->denormalize($data, $type, $format, $context);

        //Check if we already have the entity in the database (via path)
        /** @var StructuralDBElementRepository<T> $repo */
        $repo = $this->entityManager->getRepository($type);

        $path = $deserialized_entity->getFullPath(AbstractStructuralDBElement::PATH_DELIMITER_ARROW);
        $db_elements = $repo->getEntityByPath($path, AbstractStructuralDBElement::PATH_DELIMITER_ARROW);
        if ($db_elements !== []) {
            //We already have the entity in the database, so we can return it
            return end($db_elements);
        }


        //Check if we have created the entity in this request before (so we don't create multiple entities for the same path)
        //Entities get saved in the cache by type and path
        //We use a different cache for this then the objects created by a string value (saved in repo). However, that should not be a problem
        //unless the user data has mixed structure between json data and a string path
        if (isset($this->object_cache[$type][$path])) {
            return $this->object_cache[$type][$path];
        }

        //Save the entity in the cache
        $this->object_cache[$type][$path] = $deserialized_entity;

        //We don't have the entity in the database, so we have to persist it
        $this->entityManager->persist($deserialized_entity);

        return $deserialized_entity;
    }

    public function getSupportedTypes(): array
    {
        //Must be false, because we use in_array in supportsDenormalization
        return [
            AbstractStructuralDBElement::class => false,
        ];
    }
}
