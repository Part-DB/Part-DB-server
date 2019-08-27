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
use App\Entity\Attachments\PartAttachment;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\KernelInterface;

class AttachmentHelper
{
    /**
     * @var string The folder where the attachments are saved. By default this is data/media in the project root string
     */
    protected $base_path;

    public function __construct(ParameterBagInterface $params, KernelInterface $kernel)
    {
        $tmp_base_path = $params->get('media_directory');

        $fs = new Filesystem();

        //Determine if it is an absolute path, or if we need to create a real absolute one out of it
        if ($fs->isAbsolutePath($tmp_base_path)) {
            $this->base_path = $tmp_base_path;
        } else {
            $this->base_path = realpath($kernel->getProjectDir() . DIRECTORY_SEPARATOR . $tmp_base_path);
        }
    }

    /**
     * Returns the absolute path to the folder where all attachments are saved.
     * @return string
     */
    public function getMediaPath() : string
    {
        return $this->base_path;
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
     * Converts an relative placeholder filepath (with %MEDIA% or older %BASE%) to an absolute filepath on disk.
     * @param string $placeholder_path The filepath with placeholder for which the real path should be determined.
     * @return string The absolute real path of the file
     */
    public function placeholderToRealPath(string $placeholder_path) : string
    {
        //The new attachments use %MEDIA% as placeholders, which is the directory set in media_directory
        $placeholder_path = str_replace("%MEDIA%", $this->base_path, $placeholder_path);

        //Older path entries are given via %BASE% which was the project root
        $placeholder_path = str_replace("%BASE%/data/media", $this->base_path, $placeholder_path);

        //Normalize path
        $placeholder_path = str_replace('\\', '/', $placeholder_path);

        return $placeholder_path;
    }

    /**
     * Converts an real absolute filepath to a placeholder version.
     * @param string $real_path The absolute path, for which the placeholder version should be generated.
     * @param bool $old_version By default the %MEDIA% placeholder is used, which is directly replaced with the
     * media directory. If set to true, the old version with %BASE% will be used, which is the project directory.
     * @return string The placeholder version of the filepath
     */
    public function realPathToPlaceholder(string $real_path, bool $old_version = false) : string
    {
        if ($old_version) {
            $real_path = str_replace($this->base_path, "%BASE%/data/media", $real_path);
        } else {
            $real_path = str_replace($this->base_path, "%MEDIA%", $real_path);
        }

        //Normalize path
        $real_path = str_replace('\\', '/', $real_path);
        return $real_path;
    }

    /**
     * Returns the absolute filepath of the attachment. Null is returned, if the attachment is externally saved.
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

        $path = $attachment->getPath();
        $path = $this->placeholderToRealPath($path);
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
        if (empty($attachment->getPath())) {
            return false;
        }

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

    /**
     * Generate a path to a folder, where this attachment can save its file.
     * @param Attachment $attachment The attachment for which the folder should be generated
     * @return string The path to the folder (without trailing slash)
     */
    public function generateFolderForAttachment(Attachment $attachment) : string
    {
        $mapping = [PartAttachment::class => 'part'];

        $path = $this->base_path . DIRECTORY_SEPARATOR . $mapping[get_class($attachment)] . DIRECTORY_SEPARATOR . $attachment->getElement()->getID();
        return $path;
    }

    /**
     * Moves the given uploaded file to a permanent place and saves it into the attachment
     * @param Attachment $attachment The attachment in which the file should be saved
     * @param UploadedFile|null $file The file which was uploaded
     * @return Attachment The attachment with the new filepath
     */
    public function upload(Attachment $attachment, ?UploadedFile $file) : Attachment
    {
        //If file is null, do nothing (helpful, so we dont have to check if the file was reuploaded in controller)
        if (!$file) {
            return $attachment;
        }

        $folder = $this->generateFolderForAttachment($attachment);

        //Sanatize filename
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $originalFilename);
        $newFilename = $safeFilename . '.' . $file->getClientOriginalExtension();

        //If a file with this name is already existing add a number to the filename
        if (file_exists($folder . DIRECTORY_SEPARATOR . $newFilename)) {
            $bak = $newFilename;

            $number = 1;
            $newFilename = $folder . DIRECTORY_SEPARATOR . $safeFilename . '-' . $number . '.' . $file->getClientOriginalExtension();
            while (file_exists($newFilename)) {
                $number++;
                $newFilename = $folder . DIRECTORY_SEPARATOR . $safeFilename . '-' . $number . '.' . $file->getClientOriginalExtension();
            }
        }

        //Move our temporay attachment to its final location
        $file_path = $file->move($folder, $newFilename)->getRealPath();

        //Make our file path relative to %BASE%
        $file_path = $this->realPathToPlaceholder($file_path);

        //Save the path to the attachment
        $attachment->setPath($file_path);

        return $attachment;
    }

}