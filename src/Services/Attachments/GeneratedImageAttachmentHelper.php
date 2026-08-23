<?php

declare(strict_types=1);

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

namespace App\Services\Attachments;

use App\Entity\Attachments\AttachmentType;
use App\Entity\Attachments\AttachmentUpload;
use App\Entity\Attachments\PartAttachment;
use App\Entity\Parts\Part;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Creates attachments from SVG markup that was generated client-side (e.g. by the
 * resistor/capacitor value calculator) and attaches them to a part.
 */
final readonly class GeneratedImageAttachmentHelper
{
    private const ATTACHMENT_TYPE_NAME = 'Generated image';

    public function __construct(
        private EntityManagerInterface $em,
        private AttachmentSubmitHandler $submitHandler,
    ) {
    }

    /**
     * Stores the given SVG markup as a sanitized picture attachment of the part.
     *
     * @param  Part    $part           The part the image should be attached to
     * @param  string  $svg            The raw SVG markup
     * @param  string  $name           The name shown for the attachment
     * @param  bool    $setAsPreview   Whether the image should become the part's preview picture
     * @param  bool    $overwrite      Remove any previously generated image(s) first (replace instead of add)
     */
    public function attachSvgToPart(Part $part, string $svg, string $name, bool $setAsPreview = true, bool $overwrite = false): PartAttachment
    {
        $type = $this->getGeneratedImageType();

        //In overwrite mode, drop previously generated images so re-generating replaces them
        //instead of accumulating "Generated image (2)", "(3)", …
        if ($overwrite) {
            foreach ($part->getAttachments()->toArray() as $existing) {
                if ($existing->getAttachmentType()?->getName() === $type->getName()) {
                    if ($part->getMasterPictureAttachment() === $existing) {
                        $part->setMasterPictureAttachment(null);
                    }
                    $part->removeAttachment($existing);
                    $this->em->remove($existing);
                }
            }
        }

        $attachment = new PartAttachment();
        //De-duplicate the name so generating the same image twice does not violate the
        //(name, attachment_type, element) unique constraint on PartAttachment.
        $attachment->setName($this->uniqueName($part, $name !== '' ? $name : 'Generated image', $type));
        $attachment->setAttachmentType($type);
        $part->addAttachment($attachment);

        //Reuse the regular upload pipeline so the SVG is sanitized and (optionally) becomes the preview image.
        $upload = new AttachmentUpload(
            file: null,
            data: base64_encode($svg),
            filename: 'generated.svg',
            becomePreviewIfEmpty: $setAsPreview,
        );
        $this->submitHandler->handleUpload($attachment, $upload);

        //If explicitly requested, force this attachment to become the preview picture even if one already exists.
        if ($setAsPreview && $attachment->isPicture()) {
            $part->setMasterPictureAttachment($attachment);
        }

        $this->em->persist($attachment);

        return $attachment;
    }

    /**
     * Builds a name that is unique among the part's attachments of the given type,
     * appending " (2)", " (3)", … on collision (mirrors the info-provider importer).
     */
    private function uniqueName(Part $part, string $baseName, AttachmentType $type): string
    {
        $taken = [];
        foreach ($part->getAttachments() as $existing) {
            if ($existing->getAttachmentType()?->getName() === $type->getName()) {
                $taken[] = $existing->getName();
            }
        }

        if (!in_array($baseName, $taken, true)) {
            return $baseName;
        }

        $i = 2;
        while (in_array($baseName.' ('.$i.')', $taken, true)) {
            $i++;
        }

        return $baseName.' ('.$i.')';
    }

    /**
     * Returns the attachment type used for generated images, creating it if needed.
     */
    private function getGeneratedImageType(): AttachmentType
    {
        /** @var AttachmentType $type */
        $type = $this->em->getRepository(AttachmentType::class)->findOrCreateForInfoProvider(self::ATTACHMENT_TYPE_NAME);

        //A newly created type is not persisted yet, and the attachment_type relation does not cascade persist.
        if ($type->getID() === null) {
            $type->setFiletypeFilter('image/*');
            $type->setAlternativeNames(self::ATTACHMENT_TYPE_NAME);
            $this->em->persist($type);
        }

        return $type;
    }
}
