<?php
/**
 *
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/
 *
 * part-db version 0.2+
 * Copyright (C) 2009 K. Jacobs and others (see authors.php)
 * http://code.google.com/p/part-db/
 *
 * Part-DB Version 0.4+
 * Copyright (C) 2016 - 2019 Jan Böhmer
 * https://github.com/jbtronics
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 *
 */

namespace App\Services;

use App\Entity\Attachments\Attachment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;

/**
 * This service provides functions to find attachments via an reverse search based on a file.
 * @package App\Services
 */
class AttachmentReverseSearch
{
    protected $em;
    protected $attachment_helper;

    public function __construct(EntityManagerInterface $em, AttachmentHelper $attachmentHelper)
    {
        $this->em = $em;
        $this->attachment_helper = $attachmentHelper;
    }

    /**
     * Find all attachments that use the given file
     * @param File $file
     * @return Attachment[]
     */
    public function findAttachmentsByFile(\SplFileInfo $file) : array
    {
        //Path with %MEDIA%
        $relative_path_new = $this->attachment_helper->realPathToPlaceholder($file->getPathname());
        //Path with %BASE%
        $relative_path_old = $this->attachment_helper->realPathToPlaceholder($file->getPathname(), true);

        $repo = $this->em->getRepository(Attachment::class);
        return $repo->findBy(['path' => [$relative_path_new, $relative_path_old]]);
    }

    /**
     * Deletes the given file if it is not used by more than $threshold attachments
     * @param \SplFileInfo $file The file that should be removed
     * @param int $threshold The threshold used, to determine if a file should be deleted or not.
     * @return bool True, if the file was delete. False if not.
     */
    public function deleteIfNotUsed(\SplFileInfo $file, int $threshold = 0) : bool
    {
        /* When the file is used more then $threshold times, don't delete it */
        if (count($this->findAttachmentsByFile($file)) > $threshold) {
            return false;
        }

        $fs = new Filesystem();
        $fs->remove($file);

        return true;
    }
}