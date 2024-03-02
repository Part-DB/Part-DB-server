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

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use App\Entity\Attachments\Attachment;
use App\Entity\Parameters\AbstractParameter;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * The purpose of this normalizer is to automatically add the _type discriminator field for the Attachment and AbstractParameter classes
 * based on the element IRI.
 * So that for a request pointing for a part element, an PartAttachment is automatically created.
 * This highly improves UX and is the expected behavior.
 */
class DetermineTypeFromElementIRIDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    const SUPPORTED_CLASSES = [
        Attachment::class,
        AbstractParameter::class
    ];

    use DenormalizerAwareTrait;

    public function __construct(private readonly IriConverterInterface $iriConverter, private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory)
    {
    }

    /**
     * This functions add the _type discriminator to the input array if necessary automatically from the given element IRI.
     * @param  array  $input
     * @param  Operation  $operation
     * @return array
     * @throws \ApiPlatform\Metadata\Exception\ResourceClassNotFoundException
     */
    private function addTypeDiscriminatorIfNecessary(array $input, Operation $operation): array
    {

        //We only want to modify POST requests
        if (!$operation instanceof Post) {
            return $input;
        }


        //Ignore if the _type variable is already set
        if (isset($input['_type'])) {
            return $input;
        }

        if (!isset($input['element']) || !is_string($input['element'])) {
            return $input;
        }

        //Retrieve the element
        $element = $this->iriConverter->getResourceFromIri($input['element']);

        //Retrieve the short name of the operation
        $type = $this->resourceMetadataCollectionFactory->create($element::class)->getOperation()->getShortName();
        $input['_type'] = $type;

        return $input;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): object
    {
        //If we are on API platform, we want to add the type discriminator if necessary
        if (!isset($data['_type']) && isset($context['operation'])) {
            $data = $this->addTypeDiscriminatorIfNecessary($data, $context['operation']);
        }

        return $this->denormalizer->denormalize($data, $type, $format, $context);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null)
    {
        //Only denormalize if the _type discriminator is not set and the class is supported
        return is_array($data) && !isset($data['_type']) && in_array($type, self::SUPPORTED_CLASSES, true);
    }

    public function getSupportedTypes(?string $format): array
    {
        $tmp = [];

        foreach (self::SUPPORTED_CLASSES as $class) {
            $tmp[$class] = false;
        }

        return $tmp;
    }
}