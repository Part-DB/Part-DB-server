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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * @see \App\Tests\Serializer\StructuralElementFromNameDenormalizerTest
 */
class StructuralElementFromNameDenormalizer implements DenormalizerInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function supportsDenormalization($data, string $type, string $format = null, array $context = []): bool
    {
        return is_string($data) && is_subclass_of($type, AbstractStructuralDBElement::class);
    }

    public function denormalize($data, string $type, string $format = null, array $context = []): ?AbstractStructuralDBElement
    {
        //Retrieve the repository for the given type
        /** @var StructuralDBElementRepository $repo */
        $repo = $this->em->getRepository($type);

        $path_delimiter = $context['path_delimiter'] ?? '->';

        if ($context['create_unknown_datastructures'] ?? false) {
            $elements = $repo->getNewEntityFromPath($data, $path_delimiter);
            //Persist all new elements
            foreach ($elements as $element) {
                $this->em->persist($element);
            }
            if ($elements === []) {
                return null;
            }
            return end($elements);
        }

        $elements = $repo->getEntityByPath($data, $path_delimiter);
        if ($elements === []) {
            return null;
        }
        return end($elements);
    }

    public function getSupportedTypes(?string $format)
    {
        //Cachable value Must be false, because we do an is_string check on data in supportsDenormalization
        return [
            AbstractStructuralDBElement::class => false
        ];
    }
}
