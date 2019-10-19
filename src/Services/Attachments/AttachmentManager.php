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

namespace App\Services\Attachments;


use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\AttachmentContainingDBElement;
use App\Entity\Attachments\AttachmentTypeAttachment;
use App\Entity\Attachments\CategoryAttachment;
use App\Entity\Attachments\CurrencyAttachment;
use App\Entity\Attachments\DeviceAttachment;
use App\Entity\Attachments\FootprintAttachment;
use App\Entity\Attachments\GroupAttachment;
use App\Entity\Attachments\ManufacturerAttachment;
use App\Entity\Attachments\MeasurementUnitAttachment;
use App\Entity\Attachments\PartAttachment;
use App\Entity\Attachments\StorelocationAttachment;
use App\Entity\Attachments\SupplierAttachment;
use App\Entity\Attachments\UserAttachment;
use App\Services\Attachments\AttachmentPathResolver;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * This service contains basic commonly used functions to work with attachments.
 * Especially this services gives you important infos about attachments, that can not be retrieved via the entities
 * (like filesize or if a file is existing).
 *
 * Special operations like getting attachment urls or handling file uploading/downloading are in their own services.
 * @package App\Services
 */
class AttachmentManager
{

    protected $pathResolver;

    public function __construct(AttachmentPathResolver $pathResolver)
    {
        $this->pathResolver = $pathResolver;
    }

    /**
     * Gets an SPLFileInfo object representing the file associated with the attachment.
     * @param Attachment $attachment The attachment for which the file should be generated
     * @return \SplFileInfo|null The fileinfo for the attachment file. Null, if the attachment is external or has
     * invalid file.
     */
    public function attachmentToFile(Attachment $attachment) : ?\SplFileInfo
    {
        if ($attachment->isExternal() || !$this->isFileExisting($attachment)) {
            return null;
        }

        return new \SplFileInfo($this->toAbsoluteFilePath($attachment));
    }

    /**
     * Returns the absolute filepath of the attachment. Null is returned, if the attachment is externally saved,
     * or is not existing.
     * @param Attachment $attachment The attachment for which the filepath should be determined
     * @return string|null
     */
    public function toAbsoluteFilePath(Attachment $attachment): ?string
    {
        if (empty($attachment->getPath())) {
            return null;
        }

        if ($attachment->isExternal()) {
            return null;
        }

        $path = $this->pathResolver->placeholderToRealPath($attachment->getPath());

        //realpath does not work with null as argument
        if ($path === null) {
            return null;
        }

        $tmp = realpath($path);
        //If path is not existing realpath returns false.
        if ($tmp === false) {
            return null;
        }
        return $tmp;
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
        if (empty($attachment->getPath())) {
            return false;
        }

        return file_exists($this->toAbsoluteFilePath($attachment)) || $attachment->isExternal();
    }

    /**
     * Returns the filesize of the attachments in bytes.
     * For external attachments or not existing attachments, null is returned.
     *
     * @param Attachment $attachment The filesize for which the filesize should be calculated.
     * @return int|null
     */
    public function getFileSize(Attachment $attachment): ?int
    {
        if ($attachment->isExternal()) {
            return null;
        }

        if (!$this->isFileExisting($attachment)) {
            return null;
        }

        $tmp = filesize($this->toAbsoluteFilePath($attachment));
        return  $tmp !== false ? $tmp : null;
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