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


use App\Entity\Attachment;
use Doctrine\ORM\EntityManagerInterface;
use SebastianBergmann\CodeCoverage\Node\File;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

class AttachmentHelper
{

    protected $base_path;

    public function __construct(ParameterBagInterface $params, KernelInterface $kernel)
    {
        $tmp_base_path = $params->get('media_directory');

        $fs = new Filesystem();

        //Determine if it is an absolute path, or if we need to create a real absolute one out of it
        if ($fs->isAbsolutePath($tmp_base_path)) {
            $this->base_path = $tmp_base_path;
        } else {
            $this->base_path = realpath($kernel->getProjectDir() . $tmp_base_path);
        }
    }

    /**
     * Returns the absolute filepath of the attachment. Null is returned, if the attachment is externally saved.
     * @param Attachment $attachment The attachment for which the filepath should be determined
     * @return string|null
     */
    public function toAbsoluteFilePath(Attachment $attachment): ?string
    {
        if ($attachment->isExternal()) {
            return null;
        }

        $path = $attachment->getPath();
        $path = str_replace("%BASE%", $this->base_path, $path);
        return realpath($path);
    }

    /**
     * Checks if the file in this attachement is existing. This works for files on the HDD, and for URLs
     * (it's not checked if the ressource behind the URL is really existing, so for every external attachment true is returned).
     *
     * @param Attachment $attachment The attachment for which the existence should be checked
     *
     * @return bool True if the file is existing.
     */
    public function isFileExisting(Attachment $attachment): bool
    {
        return file_exists($this->toAbsoluteFilePath($attachment)) || $attachment->isExternal();
    }

    /**
     * Returns the filesize of the attachments in bytes.
     * For external attachments, null is returned.
     *
     * @param Attachment $attachment The filesize for which the filesize should be calculated.
     * @return int|null
     */
    public function getFileSize(Attachment $attachment): ?int
    {
        if ($attachment->isExternal()) {
            return null;
        }

        return filesize($this->toAbsoluteFilePath($attachment));
    }

    /**
     * Returns a human readable version of the attachment file size.
     * For external attachments, null is returned.
     *
     * @param Attachment $attachment
     * @param int $decimals The number of decimals numbers that should be printed
     * @return string|null A string like 1.3M
     */
    public function getHumanFileSize(Attachment $attachment, $decimals = 2): ?string
    {
        $bytes = $this->getFileSize($attachment);

        if ($bytes == null) {
            return null;
        }

        //Format filesize for human reading
        //Taken from: https://www.php.net/manual/de/function.filesize.php#106569 and slightly modified

        $sz = 'BKMGTP';
        $factor = (int) floor((strlen($bytes) - 1) / 3);
        return sprintf("%.{$decimals}f", $bytes / 1024 ** $factor) . @$sz[$factor];
    }

}