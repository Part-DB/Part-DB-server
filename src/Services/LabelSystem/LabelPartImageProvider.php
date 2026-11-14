<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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
namespace App\Services\LabelSystem;

use App\Entity\Attachments\Attachment;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Services\Attachments\AttachmentManager;
use App\Services\Attachments\PartPreviewGenerator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Mime\MimeTypesInterface;

/**
 * Provides the main image of a part (the same image as shown in the parts table), to be embedded in a label.
 * dompdf can not load remote resources, so the image is returned as data URI, downscaled to a size suitable for labels.
 */
final class LabelPartImageProvider
{
    /** Images larger than this (in px) are downscaled, which is still enough for about 50mm at 300dpi */
    private const MAX_SIZE = 600;

    /** @var array<int|string, array{uri: string, width: int, height: int}|null> Cache by attachment, as labels for many part lots often use the same image */
    private array $cache = [];

    public function __construct(
        private readonly PartPreviewGenerator $previewGenerator,
        private readonly AttachmentManager $attachmentManager,
        private readonly Security $security,
        private readonly MimeTypesInterface $mimeTypes,
    ) {
    }

    /**
     * Returns the part for the given label target, if it can have a part image (parts and part lots).
     */
    public function getPart(object $element): ?Part
    {
        return match (true) {
            $element instanceof Part => $element,
            $element instanceof PartLot => $element->getPart(),
            default => null,
        };
    }

    /**
     * Returns the main image of the part of the given label target as data URI together with its size in px,
     * or null if the target has no (locally stored) image.
     * @return array{uri: string, width: int, height: int}|null
     */
    public function getImage(object $element): ?array
    {
        $part = $this->getPart($element);
        if ($part === null) {
            return null;
        }

        $attachment = $this->getAttachment($part);
        if (!$attachment instanceof Attachment) {
            return null;
        }

        $key = $attachment->getID() ?? spl_object_id($attachment);
        if (!array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $this->loadImage($attachment);
        }

        return $this->cache[$key];
    }

    /**
     * Determines the image to use for the part: The preview image of the parts table (the master picture of the part,
     * its footprint or its project), or otherwise the first picture attachment of the part.
     * Only locally stored images are used, as external images can not be embedded (they would have to be downloaded).
     * Only images the current user is allowed to view are used (like when viewing the attachment file directly).
     */
    private function getAttachment(Part $part): ?Attachment
    {
        $attachment = $this->previewGenerator->getTablePreviewAttachment($part);
        if ($attachment instanceof Attachment && $attachment->hasInternal() && $this->isViewAllowed($attachment)) {
            return $attachment;
        }

        foreach ($part->getAttachments() as $attachment) {
            if ($attachment->isPicture() && $attachment->hasInternal() && $this->isViewAllowed($attachment)
                && $this->attachmentManager->isInternalFileExisting($attachment)) {
                return $attachment;
            }
        }

        return null;
    }

    /**
     * Checks if the current user is allowed to view the file of the given attachment (the same checks as in
     * AttachmentFileController, which serves the attachment files).
     */
    private function isViewAllowed(Attachment $attachment): bool
    {
        return $this->security->isGranted('read', $attachment)
            && (!$attachment->isSecure() || $this->security->isGranted('show_private', $attachment));
    }

    /**
     * @return array{uri: string, width: int, height: int}|null
     */
    private function loadImage(Attachment $attachment): ?array
    {
        $path = $this->attachmentManager->toAbsoluteInternalFilePath($attachment);
        if ($path === null || !is_readable($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if ($content === false || $content === '') {
            return null;
        }

        $mime = $this->mimeTypes->guessMimeType($path) ?? '';

        if ($mime === 'image/svg+xml') {
            [$width, $height] = $this->getSVGSize($content);
            return ['uri' => $this->toDataUri($content, $mime), 'width' => $width, 'height' => $height];
        }

        $size = @getimagesizefromstring($content);
        if ($size === false || $size[0] <= 0 || $size[1] <= 0) {
            return null;
        }
        [$width, $height] = $size;

        //Downscale large images, so that the PDF does not get huge. PNG keeps transparency.
        if (max($width, $height) > self::MAX_SIZE) {
            $image = @imagecreatefromstring($content);
            if ($image !== false) {
                $scale = self::MAX_SIZE / max($width, $height);
                $scaled = imagescale($image, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
                if ($scaled !== false) {
                    imagealphablending($scaled, false);
                    imagesavealpha($scaled, true);
                    ob_start();
                    imagepng($scaled);
                    $content = (string) ob_get_clean();
                    $mime = 'image/png';
                    $width = imagesx($scaled);
                    $height = imagesy($scaled);
                }
            }
        }

        return ['uri' => $this->toDataUri($content, $mime), 'width' => $width, 'height' => $height];
    }

    /**
     * Determines the size of an SVG image from its width/height attributes or its viewBox. Defaults to a square.
     * @return array{int, int}
     */
    private function getSVGSize(string $svg): array
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($svg);
        libxml_use_internal_errors($previous);

        if ($xml !== false) {
            $width = (float) ($xml['width'] ?? 0);
            $height = (float) ($xml['height'] ?? 0);
            if ($width > 0 && $height > 0) {
                return [(int) round($width), (int) round($height)];
            }

            $viewBox = preg_split('/[\s,]+/', trim((string) ($xml['viewBox'] ?? ''))) ?: [];
            if (count($viewBox) === 4 && (float) $viewBox[2] > 0 && (float) $viewBox[3] > 0) {
                return [(int) round((float) $viewBox[2]), (int) round((float) $viewBox[3])];
            }
        }

        return [100, 100];
    }

    private function toDataUri(string $content, string $mime): string
    {
        return 'data:'.$mime.';base64,'.base64_encode($content);
    }
}
