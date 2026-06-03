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

    private const PARENT_ELEMENT = 'STRUCTURAL_DENORMALIZER_PARENT_ELEMENT';

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

        //In the first step, denormalize without children
        $context_without_children = $context;
        $context_without_children['groups'] = array_filter(
            $context_without_children['groups'] ?? [],
            static fn($group) => $group !== 'include_children',
        );
        //Also unset any parent element, to avoid infinite loops. We will set the parent element in the next step, when we denormalize the children
        unset($context_without_children[self::PARENT_ELEMENT]);
        /** @var AbstractStructuralDBElement $entity */
        $entity = $this->denormalizer->denormalize($data, $type, $format, $context_without_children);

        //Assign the parent element to the denormalized entity, so it can be used in the denormalization of the children (e.g. for path generation)
        if (isset($context[self::PARENT_ELEMENT]) && $context[self::PARENT_ELEMENT] instanceof $entity && $entity->getID() === null) {
            $entity->setParent($context[self::PARENT_ELEMENT]);
        }

        //Check if we already have the entity in the database (via path)
        /** @var StructuralDBElementRepository<T> $repo */
        $repo = $this->entityManager->getRepository($type);

        $path = $entity->getFullPath(AbstractStructuralDBElement::PATH_DELIMITER_ARROW);
        $db_elements = $repo->getEntityByPath($path, AbstractStructuralDBElement::PATH_DELIMITER_ARROW);
        if ($db_elements !== []) {
            //We already have the entity in the database, so we can return it
            $entity = end($db_elements);
        }


        //Check if we have created the entity in this request before (so we don't create multiple entities for the same path)
        //Entities get saved in the cache by type and path
        //We use a different cache for this then the objects created by a string value (saved in repo). However, that should not be a problem
        //unless the user data has mixed structure between JSON data and a string path
        if (isset($this->object_cache[$type][$path])) {
            $entity = $this->object_cache[$type][$path];
        } else {
            //Save the entity in the cache
            $this->object_cache[$type][$path] = $entity;
        }

        //In the next step we can denormalize the children, and add our children to the entity.
        if (in_array('include_children', $context['groups'], true) && isset($data['children']) && is_array($data['children'])) {
            foreach ($data['children'] as $child_data) {
                $child_entity = $this->denormalize($child_data, $type, $format, array_merge($context, [self::PARENT_ELEMENT => $entity]));
                if ($child_entity !== null && !$entity->getChildren()->contains($child_entity)) {
                    $entity->addChild($child_entity);
                }
            }
        }

        //We don't have the entity in the database, so we have to persist it
        $this->entityManager->persist($entity);

        return $entity;
    }

    public function getSupportedTypes(?string $format): array
    {
        //Must be false, because we use in_array in supportsDenormalization
        return [
            AbstractStructuralDBElement::class => false,
        ];
    }
}
