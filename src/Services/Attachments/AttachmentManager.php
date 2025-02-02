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
use SplFileInfo;
use function strlen;

/**
 * This service contains basic commonly used functions to work with attachments.
 * Especially this services gives you important infos about attachments, that can not be retrieved via the entities
 * (like filesize or if a file is existing).
 *
 * Special operations like getting attachment urls or handling file uploading/downloading are in their own services.
 */
class AttachmentManager
{
    public function __construct(protected AttachmentPathResolver $pathResolver)
    {
    }

    /**
     * Gets an SPLFileInfo object representing the file associated with the attachment.
     *
     * @param Attachment $attachment The attachment for which the file should be generated
     *
     * @return SplFileInfo|null The fileinfo for the attachment file. Null, if the attachment is only external or has
     *                          invalid file.
     */
    public function attachmentToFile(Attachment $attachment): ?SplFileInfo
    {
        if (!$this->isInternalFileExisting($attachment)) {
            return null;
        }

        return new SplFileInfo($this->toAbsoluteInternalFilePath($attachment));
    }

    /**
     * Returns the absolute filepath to the internal copy of the attachment. Null is returned, if the attachment is
     * only externally saved, or is not existing.
     *
     * @param Attachment $attachment The attachment for which the filepath should be determined
     */
    public function toAbsoluteInternalFilePath(Attachment $attachment): ?string
    {
        if (!$attachment->hasInternal()){
            return null;
        }

        $path = $this->pathResolver->placeholderToRealPath($attachment->getInternalPath());

        //realpath does not work with null as argument
        if (null === $path) {
            return null;
        }

        $tmp = realpath($path);
        //If path is not existing realpath returns false.
        if (false === $tmp) {
            return null;
        }

        return $tmp;
    }

    /**
     * Checks if the file in this attachment is existing. This works for files on the HDD, and for URLs
     * (it's not checked if the resource behind the URL is really existing, so for every external attachment true is returned).
     *
     * @param Attachment $attachment The attachment for which the existence should be checked
     *
     * @return bool true if the file is existing
     */
    public function isFileExisting(Attachment $attachment): bool
    {
        if($attachment->hasExternal()){
            return true;
        }
        return $this->isInternalFileExisting($attachment);
    }

    /**
     * Checks if the internal file in this attachment is existing. Returns false if the attachment doesn't have an
     * internal file.
     *
     * @param Attachment $attachment The attachment for which the existence should be checked
     *
     * @return bool true if the file is existing
     */
    public function isInternalFileExisting(Attachment $attachment): bool
    {
        $absolute_path = $this->toAbsoluteInternalFilePath($attachment);

        if (null === $absolute_path) {
            return false;
        }

        return file_exists($absolute_path);
    }

    /**
     * Returns the filesize of the attachments in bytes.
     * For purely external attachments or inexistent attachments, null is returned.
     *
     * @param Attachment $attachment the filesize for which the filesize should be calculated
     */
    public function getFileSize(Attachment $attachment): ?int
    {
        if (!$this->isInternalFileExisting($attachment)) {
            return null;
        }

        $tmp = filesize($this->toAbsoluteInternalFilePath($attachment));

        return  false !== $tmp ? $tmp : null;
    }

    /**
     * Returns a human-readable version of the attachment file size.
     * For external attachments, null is returned.
     *
     * @param  int  $decimals The number of decimals numbers that should be printed
     *
     * @return string|null A string like 1.3M
     */
    public function getHumanFileSize(Attachment $attachment, int $decimals = 2): ?string
    {
        $bytes = $this->getFileSize($attachment);

        if (null === $bytes) {
            return null;
        }

        //Format filesize for human reading
        //Taken from: https://www.php.net/manual/de/function.filesize.php#106569 and slightly modified

        $sz = 'BKMGTP';
        $factor = (int) floor((strlen((string) $bytes) - 1) / 3);
        //Use real (10 based) SI prefixes
        return sprintf("%.{$decimals}f", $bytes / 1000 ** $factor).@$sz[$factor];
    }
}
