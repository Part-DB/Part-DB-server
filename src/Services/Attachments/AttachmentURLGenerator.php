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

namespace App\Services\Attachments;

use Imagine\Exception\RuntimeException;
use App\Entity\Attachments\Attachment;
use InvalidArgumentException;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Psr\Log\LoggerInterface;
use function strlen;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @see \App\Tests\Services\Attachments\AttachmentURLGeneratorTest
 */
class AttachmentURLGenerator
{
    protected string $public_path;

    public function __construct(protected Packages $assets, protected AttachmentPathResolver $pathResolver,
                                protected UrlGeneratorInterface $urlGenerator, protected AttachmentManager $attachmentHelper,
        protected CacheManager $thumbnailManager, protected LoggerInterface $logger)
    {
        //Determine a normalized path to the public folder (assets are relative to this folder)
        $this->public_path = $this->pathResolver->parameterToAbsolutePath('public');
    }

    /**
     * Converts the absolute file path to a version relative to the public folder, that can be passed to the
     * Asset Component functions.
     *
     * @param string      $absolute_path the absolute path that should be converted
     * @param string|null $public_path   The public path to which the relative pathes should be created.
     *                                   The path must NOT have a trailing slash!
     *                                   If this is set to null, the global public/ folder is used.
     *
     * @return string|null The relative version of the string. Null if the absolute path was not a child folder
     *                     of public path
     */
    public function absolutePathToAssetPath(string $absolute_path, ?string $public_path = null): ?string
    {
        //Normalize file path (public path, use / as file path)
        $absolute_path = str_replace('\\', '/', $absolute_path);

        if (null === $public_path) {
            $public_path = $this->public_path;
        }

        //Our absolute path must begin with public path, or we can not use it for asset pathes.
        if (!str_starts_with($absolute_path, $public_path)) {
            return null;
        }

        //Return the part relative after public path.
        return substr($absolute_path, strlen($public_path) + 1);
    }

    /**
     * Converts a placeholder path to a path to an image path.
     *
     * @param string $placeholder_path the placeholder path that should be converted
     */
    public function placeholderPathToAssetPath(string $placeholder_path): ?string
    {
        $absolute_path = $this->pathResolver->placeholderToRealPath($placeholder_path);

        return $this->absolutePathToAssetPath($absolute_path);
    }

    /**
     * Returns a URL under which the attachment file can be viewed.
     * @return string|null The URL or null if the attachment file is not existing
     */
    public function getInternalViewURL(Attachment $attachment): ?string
    {
        $absolute_path = $this->attachmentHelper->toAbsoluteInternalFilePath($attachment);
        if (null === $absolute_path) {
            return null;
        }

        $asset_path = $this->absolutePathToAssetPath($absolute_path);
        //If path is not relative to public path or marked as secure, serve it via controller
        if (null === $asset_path || $attachment->isSecure()) {
            return $this->urlGenerator->generate('attachment_view', ['id' => $attachment->getID()]);
        }

        //Otherwise we can serve the relative path via Asset component
        return $this->assets->getUrl($asset_path);
    }

    /**
     * Returns a URL to a thumbnail of the attachment file.
     * For external files the original URL is returned.
     * @return string|null The URL or null if the attachment file is not existing
     */
    public function getThumbnailURL(Attachment $attachment, string $filter_name = 'thumbnail_sm'): ?string
    {
        if (!$attachment->isPicture()) {
            throw new InvalidArgumentException('Thumbnail creation only works for picture attachments!');
        }

        if (!$attachment->hasInternal()){
            if($attachment->hasExternal()) {
                return $attachment->getExternalPath();
            }
            return null;
        }

        $absolute_path = $this->attachmentHelper->toAbsoluteInternalFilePath($attachment);
        if (null === $absolute_path) {
            return null;
        }

        $asset_path = $this->absolutePathToAssetPath($absolute_path);
        //If path is not relative to public path or marked as secure, serve it via controller
        if (null === $asset_path || $attachment->isSecure()) {
            return $this->urlGenerator->generate('attachment_view', ['id' => $attachment->getID()]);
        }

        //GD can not work with SVG, so serve it directly...
        //We can not use getExtension here, because it uses the original filename and not the real extension
        //Instead we use the logic, which is also used to determine if the attachment is a picture
        $extension = pathinfo(parse_url($attachment->getInternalPath(), PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);
        if ('svg' === $extension) {
            return $this->assets->getUrl($asset_path);
        }

        try {
            //We try to get network path here (so no schema), but this param might just get ignored by the cache manager
            $tmp = $this->thumbnailManager->getBrowserPath($asset_path, $filter_name, [], null, UrlGeneratorInterface::NETWORK_PATH);
            //So we remove the schema manually
            return preg_replace('/^https?:/', '', $tmp);
        } catch (RuntimeException $e) {
            //If the filter fails, we can not serve the thumbnail and fall back to the original image and log a warning
            $this->logger->warning('Could not open thumbnail for attachment with ID ' . $attachment->getID() . ': ' . $e->getMessage());
            return $this->assets->getUrl($asset_path);
        }
    }

    /**
     * Returns a download link to the file associated with the attachment.
     */
    public function getInternalDownloadURL(Attachment $attachment): string
    {
        //Redirect always to download controller, which sets the correct headers for downloading:
        return $this->urlGenerator->generate('attachment_download', ['id' => $attachment->getID()]);
    }
}
