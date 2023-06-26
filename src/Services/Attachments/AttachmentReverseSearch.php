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

use App\Entity\Attachments\Attachment;
use function count;
use Doctrine\ORM\EntityManagerInterface;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use SplFileInfo;
use Symfony\Component\Filesystem\Filesystem;

/**
 * This service provides functions to find attachments via a reverse search based on a file.
 */
class AttachmentReverseSearch
{
    public function __construct(protected EntityManagerInterface $em, protected AttachmentPathResolver $pathResolver, protected CacheManager $cacheManager, protected AttachmentURLGenerator $attachmentURLGenerator)
    {
    }

    /**
     * Find all attachments that use the given file.
     *
     * @param SplFileInfo $file The file for which is searched
     *
     * @return Attachment[] a list of attachments that use the given file
     */
    public function findAttachmentsByFile(SplFileInfo $file): array
    {
        //Path with %MEDIA%
        $relative_path_new = $this->pathResolver->realPathToPlaceholder($file->getPathname());
        //Path with %BASE%
        $relative_path_old = $this->pathResolver->realPathToPlaceholder($file->getPathname(), true);

        $repo = $this->em->getRepository(Attachment::class);

        return $repo->findBy([
            'path' => [$relative_path_new, $relative_path_old],
        ]);
    }

    /**
     * Deletes the given file if it is not used by more than $threshold attachments.
     *
     * @param SplFileInfo $file      The file that should be removed
     * @param int         $threshold the threshold used, to determine if a file should be deleted or not
     *
     * @return bool True, if the file was deleted. False if not.
     */
    public function deleteIfNotUsed(SplFileInfo $file, int $threshold = 1): bool
    {
        /* When the file is used more than $threshold times, don't delete it */
        if (count($this->findAttachmentsByFile($file)) > $threshold) {
            return false;
        }

        //Remove file from liip image cache
        $this->cacheManager->remove($this->attachmentURLGenerator->absolutePathToAssetPath($file->getPathname()));

        $fs = new Filesystem();
        $fs->remove($file->getPathname());

        return true;
    }
}
