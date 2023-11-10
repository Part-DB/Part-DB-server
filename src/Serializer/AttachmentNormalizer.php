<?php
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

declare(strict_types=1);


namespace App\Serializer;

use App\Entity\Attachments\Attachment;
use App\Services\Attachments\AttachmentURLGenerator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class AttachmentNormalizer implements NormalizerInterface
{
    public function __construct(
        #[Autowire(service: ObjectNormalizer::class)]
        private readonly NormalizerInterface $normalizer,
        private readonly AttachmentURLGenerator $attachmentURLGenerator,
    )
    {

    }

    public function normalize(mixed $object, string $format = null, array $context = []): array|null
    {
        if (!$object instanceof Attachment) {
            throw new \InvalidArgumentException('This normalizer only supports Attachment objects!');
        }

        $data = $this->normalizer->normalize($object, $format, $context);

        $data['media_url'] = $this->attachmentURLGenerator->getViewURL($object);
        //Add thumbnail url if the attachment is a picture
        $data['thumbnail_url'] = $object->isPicture() ? $this->attachmentURLGenerator->getThumbnailURL($object) : null;

        return $data;
    }

    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool
    {
        return $data instanceof Attachment;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            //We depend on the context to determine if we should normalize or not
            Attachment::class => true,
        ];
    }
}