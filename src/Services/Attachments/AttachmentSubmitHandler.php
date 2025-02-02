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
use App\Entity\Attachments\AttachmentContainingDBElement;
use App\Entity\Attachments\AttachmentType;
use App\Entity\Attachments\AttachmentTypeAttachment;
use App\Entity\Attachments\AttachmentUpload;
use App\Entity\Attachments\CategoryAttachment;
use App\Entity\Attachments\CurrencyAttachment;
use App\Entity\Attachments\LabelAttachment;
use App\Entity\Attachments\ProjectAttachment;
use App\Entity\Attachments\FootprintAttachment;
use App\Entity\Attachments\GroupAttachment;
use App\Entity\Attachments\ManufacturerAttachment;
use App\Entity\Attachments\MeasurementUnitAttachment;
use App\Entity\Attachments\PartAttachment;
use App\Entity\Attachments\StorageLocationAttachment;
use App\Entity\Attachments\SupplierAttachment;
use App\Entity\Attachments\UserAttachment;
use App\Exceptions\AttachmentDownloadException;
use Hshn\Base64EncodedFile\HttpFoundation\File\Base64EncodedFile;
use Hshn\Base64EncodedFile\HttpFoundation\File\UploadedBase64EncodedFile;
use const DIRECTORY_SEPARATOR;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\MimeTypesInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * This service handles the form submitting of an attachment and handles things like file uploading and downloading.
 */
class AttachmentSubmitHandler
{
    protected array $folder_mapping;

    private ?int $max_upload_size_bytes = null;

    protected const BLACKLISTED_EXTENSIONS = ['php', 'phtml', 'php3', 'ph3', 'php4', 'ph4', 'php5', 'ph5', 'phtm', 'sh',
        'asp', 'cgi', 'py', 'pl', 'exe', 'aspx', 'js', 'mjs', 'jsp', 'css', 'jar', 'html', 'htm', 'shtm', 'shtml', 'htaccess',
        'htpasswd', ''];

    public function __construct(protected AttachmentPathResolver $pathResolver, protected bool $allow_attachments_downloads,
        protected HttpClientInterface $httpClient, protected MimeTypesInterface $mimeTypes,
        protected FileTypeFilterTools $filterTools, /**
         * @var string The user configured maximum upload size. This is a string like "10M" or "1G" and will be converted to
         */
        protected string $max_upload_size)
    {
        //The mapping used to determine which folder will be used for an attachment type
        $this->folder_mapping = [
            PartAttachment::class => 'part',
            AttachmentTypeAttachment::class => 'attachment_type',
            CategoryAttachment::class => 'category',
            CurrencyAttachment::class => 'currency',
            ProjectAttachment::class => 'project',
            FootprintAttachment::class => 'footprint',
            GroupAttachment::class => 'group',
            ManufacturerAttachment::class => 'manufacturer',
            MeasurementUnitAttachment::class => 'measurement_unit',
            StorageLocationAttachment::class => 'storelocation',
            SupplierAttachment::class => 'supplier',
            UserAttachment::class => 'user',
            LabelAttachment::class => 'label_profile',
        ];
    }

    /**
     * Check if the extension of the uploaded file is allowed for the given attachment type.
     * Returns true, if the file is allowed, false if not.
     */
    public function isValidFileExtension(AttachmentType $attachment_type, UploadedFile $uploadedFile): bool
    {
        //Only validate if the attachment type has specified a filetype filter:
        if ($attachment_type->getFiletypeFilter() === '') {
            return true;
        }

        return $this->filterTools->isExtensionAllowed(
            $attachment_type->getFiletypeFilter(),
            $uploadedFile->getClientOriginalExtension()
        );
    }

    /**
     * Generates a filename for the given attachment and extension.
     * The filename contains a random id, so every time this function is called you get a unique name.
     *
     * @param Attachment $attachment The attachment that should be used for generating an attachment
     * @param string     $extension  The extension that the new file should have (must only contain chars allowed in paths)
     *
     * @return string the new filename
     */
    public function generateAttachmentFilename(Attachment $attachment, string $extension): string
    {
        //Normalize extension
        $extension = transliterator_transliterate(
            'Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()',
            $extension
        );

        //Use the (sanatized) attachment name as a filename part
        $safeName = transliterator_transliterate(
            'Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()',
            $attachment->getName()
        );

        return $safeName.'-'.uniqid('', false).'.'.$extension;
    }

    /**
     * Generates an (absolute) path to a folder where the given attachment should be stored.
     *
     * @param Attachment $attachment    The attachment that should be used for
     * @param bool       $secure_upload True if the file path should be located in a safe location
     *
     * @return string the absolute path for the attachment folder
     */
    public function generateAttachmentPath(Attachment $attachment, bool $secure_upload = false): string
    {
        $base_path = $secure_upload ? $this->pathResolver->getSecurePath() : $this->pathResolver->getMediaPath();

        //Ensure the attachment has an assigned element
        if (!$attachment->getElement() instanceof AttachmentContainingDBElement) {
            throw new InvalidArgumentException('The given attachment is not assigned to an element! An element is needed to generate a path!');
        }

        //Determine the folder prefix for the given attachment class:
        $prefix = null;
        //Check if we can use the class name dire
        if (isset($this->folder_mapping[$attachment::class])) {
            $prefix = $this->folder_mapping[$attachment::class];
        } else {
            //If not, check for instance of:
            foreach ($this->folder_mapping as $class => $folder) {
                if ($attachment instanceof $class) {
                    $prefix = $folder;
                    break;
                }
            }
        }

        //Ensure the given attachment class is known to mapping
        if (!$prefix) {
            throw new InvalidArgumentException('The given attachment class is not known! The passed class was: '.$attachment::class);
        }

        //Build path
        return
            $base_path.DIRECTORY_SEPARATOR //Base path
            .$prefix.DIRECTORY_SEPARATOR.$attachment->getElement()->getID();
    }

    /**
     * Handle submission of an attachment form.
     * This function will move the uploaded file or download the URL file to server, if needed.
     *
     * @param Attachment        $attachment the attachment that should be used for handling
     * @param AttachmentUpload|null  $upload     The upload options DTO. If it is null, it will be tried to get from the attachment option
     *
     * @return Attachment The attachment with the new filename (same instance as passed $attachment)
     */
    public function handleUpload(Attachment $attachment, ?AttachmentUpload $upload): Attachment
    {
        if ($upload === null) {
            $upload = $attachment->getUpload();
            if ($upload === null) {
                throw new InvalidArgumentException('No upload options given and no upload options set in attachment!');
            }
        }

        $file = $upload->file;

        //If no file was uploaded, but we have base64 encoded data, create a file from it
        if (!$file && $upload->data !== null) {
            $file = new UploadedBase64EncodedFile(new Base64EncodedFile($upload->data), $upload->filename ?? 'base64');
        }

        //By default we assume a public upload
        $secure_attachment = $upload->private ?? false;

        //When a file is given then upload it, otherwise check if we need to download the URL
        if ($file instanceof UploadedFile) {

            $this->upload($attachment, $file, $secure_attachment);
        } elseif ($upload->downloadUrl && $attachment->hasExternal()) {
            $this->downloadURL($attachment, $secure_attachment);
        }

        //Move the attachment files to secure location (and back) if needed
        $this->moveFile($attachment, $secure_attachment);

        //Rename blacklisted (unsecure) files to a better extension
        $this->renameBlacklistedExtensions($attachment);

        //Set / Unset the master picture attachment / preview image
        $element = $attachment->getElement();
        if ($element instanceof AttachmentContainingDBElement) {
            //Make this attachment the master picture if needed and this was requested
            if ($upload->becomePreviewIfEmpty
                && $element->getMasterPictureAttachment() === null  //Element must not have an preview image yet
                && null === $attachment->getID()                    //Attachment must be null
                && $attachment->isPicture()                         //Attachment must be a picture
            ) {
                $element->setMasterPictureAttachment($attachment);
            }

            //If this attachment is the master picture, but is not a picture anymore, dont use it as master picture anymore
            if ($element->getMasterPictureAttachment() === $attachment && !$attachment->isPicture()) {
                $element->setMasterPictureAttachment(null);
            }
        }

        return $attachment;
    }

    /**
     * Rename attachments with an unsafe extension (meaning files which would be run by a  to a safe one).
     */
    protected function renameBlacklistedExtensions(Attachment $attachment): Attachment
    {
        //We can not do anything on builtins or external ressources
        if ($attachment->isBuiltIn() || !$attachment->hasInternal()) {
            return $attachment;
        }

        //Determine the old filepath
        $old_path = $this->pathResolver->placeholderToRealPath($attachment->getInternalPath());
        if ($old_path === null || $old_path === '' || !file_exists($old_path)) {
            return $attachment;
        }
        $filename = basename($old_path);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));


        //Check if the extension is blacklisted and replace the file extension with txt if needed
        if(in_array($ext, self::BLACKLISTED_EXTENSIONS, true)) {
            $new_path = $this->generateAttachmentPath($attachment, $attachment->isSecure())
                .DIRECTORY_SEPARATOR.$this->generateAttachmentFilename($attachment, 'txt');

            //Move file to new directory
            $fs = new Filesystem();
            $fs->rename($old_path, $new_path);

            //Update the attachment
            $attachment->setInternalPath($this->pathResolver->realPathToPlaceholder($new_path));
        }


        return $attachment;
    }

    /**
     * Move the internal copy of the given attachment to a secure location (or back to public folder) if needed.
     *
     * @param Attachment $attachment      the attachment for which the file should be moved
     * @param bool       $secure_location this value determines, if the attachment is moved to the secure or public folder
     *
     * @return Attachment The attachment with the updated internal filepath
     */
    protected function moveFile(Attachment $attachment, bool $secure_location): Attachment
    {
        //We can not do anything on builtins or external ressources
        if ($attachment->isBuiltIn() || !$attachment->hasInternal()) {
            return $attachment;
        }

        //Check if we need to move the file
        if ($secure_location === $attachment->isSecure()) {
            return $attachment;
        }

        //Determine the old filepath
        $old_path = $this->pathResolver->placeholderToRealPath($attachment->getInternalPath());
        if (!file_exists($old_path)) {
            return $attachment;
        }

        $filename = basename((string) $old_path);
        //If the basename is not one of the new unique on, we have to save the old filename
        if (!preg_match('#\w+-\w{13}\.#', $filename)) {
            //Save filename to attachment field
            $attachment->setFilename($attachment->getFilename());
        }

        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $new_path = $this->generateAttachmentPath($attachment, $secure_location)
            .DIRECTORY_SEPARATOR.$this->generateAttachmentFilename($attachment, $ext);

        //Move file to new directory
        $fs = new Filesystem();
        //Ensure that the new path exists
        $fs->mkdir(dirname($new_path));
        $fs->rename($old_path, $new_path);

        //Save info to attachment entity
        $new_path = $this->pathResolver->realPathToPlaceholder($new_path);
        $attachment->setInternalPath($new_path);

        return $attachment;
    }

    /**
     * Download the URL set in the attachment and save it on the server.
     *
     * @param bool $secureAttachment True if the file should be moved to the secure attachment storage
     *
     * @return Attachment The attachment with the downloaded copy
     */
    protected function downloadURL(Attachment $attachment, bool $secureAttachment): Attachment
    {
        //Check if we are allowed to download files
        if (!$this->allow_attachments_downloads) {
            throw new RuntimeException('Download of attachments is not allowed!');
        }

        $url = $attachment->getExternalPath();

        $fs = new Filesystem();
        $attachment_folder = $this->generateAttachmentPath($attachment, $secureAttachment);
        $tmp_path = $attachment_folder.DIRECTORY_SEPARATOR.$this->generateAttachmentFilename($attachment, 'tmp');

        try {
            $response = $this->httpClient->request('GET', $url, [
                'buffer' => false,
            ]);

            if (200 !== $response->getStatusCode()) {
                throw new AttachmentDownloadException('Status code: '.$response->getStatusCode());
            }

            //Open a temporary file in the attachment folder
            $fs->mkdir($attachment_folder);
            $fileHandler = fopen($tmp_path, 'wb');
            //Write the downloaded data to file
            foreach ($this->httpClient->stream($response) as $chunk) {
                fwrite($fileHandler, $chunk->getContent());
            }
            fclose($fileHandler);

            //File download should be finished here, so determine the new filename and extension
            $headers = $response->getHeaders();
            //Try to determine a filename
            $filename = '';

            //If a content disposition header was set try to extract the filename out of it
            if (isset($headers['content-disposition'])) {
                $tmp = [];
                //Only use the filename if the regex matches properly
                if (preg_match('/[^;\\n=]*=([\'\"])*(.*)(?(1)\1|)/', $headers['content-disposition'][0], $tmp)) {
                    $filename = $tmp[2];
                }
            }

            //If we don't know filename yet, try to determine it out of url
            if ('' === $filename) {
                $filename = basename(parse_url((string) $url, PHP_URL_PATH));
            }

            //Set original file
            $attachment->setFilename($filename);

            //Check if we have an extension given
            $pathinfo = pathinfo($filename);
            if (isset($pathinfo['extension']) && $pathinfo['extension'] !== '') {
                $new_ext = $pathinfo['extension'];
            } else { //Otherwise we have to guess the extension for the new file, based on its content
                $new_ext = $this->mimeTypes->getExtensions($this->mimeTypes->guessMimeType($tmp_path))[0] ?? 'tmp';
            }

            //Rename the file to its new name and save path to attachment entity
            $new_path = $attachment_folder.DIRECTORY_SEPARATOR.$this->generateAttachmentFilename($attachment, $new_ext);
            $fs->rename($tmp_path, $new_path);

            //Make our file path relative to %BASE%
            $new_path = $this->pathResolver->realPathToPlaceholder($new_path);
            //Save the path to the attachment
            $attachment->setInternalPath($new_path);
        } catch (TransportExceptionInterface) {
            throw new AttachmentDownloadException('Transport error!');
        }

        return $attachment;
    }

    /**
     * Moves the given uploaded file to a permanent place and saves it into the attachment.
     *
     * @param Attachment   $attachment The attachment in which the file should be saved
     * @param UploadedFile $file       The file which was uploaded
     * @param bool         $secureAttachment True if the file should be moved to the secure attachment storage
     *
     * @return Attachment The attachment with the new filepath
     */
    protected function upload(Attachment $attachment, UploadedFile $file, bool $secureAttachment): Attachment
    {
        //Move our temporary attachment to its final location
        $file_path = $file->move(
            $this->generateAttachmentPath($attachment, $secureAttachment),
            $this->generateAttachmentFilename($attachment, $file->getClientOriginalExtension())
        )->getRealPath();

        //Make our file path relative to %BASE%
        $file_path = $this->pathResolver->realPathToPlaceholder($file_path);
        //Save the path to the attachment
        $attachment->setInternalPath($file_path);
        //reset any external paths the attachment might have had
        $attachment->setExternalPath('');
        //And save original filename
        $attachment->setFilename($file->getClientOriginalName());

        return $attachment;
    }

    /**
     * Parses the given file size string and returns the size in bytes.
     * Taken from https://github.com/symfony/symfony/blob/6.2/src/Symfony/Component/Validator/Constraints/File.php
     */
    private function parseFileSizeString(string $maxSize): int
    {
        $factors = [
            'k' => 1000,
            'ki' => 1 << 10,
            'm' => 1000 * 1000,
            'mi' => 1 << 20,
            'g' => 1000 * 1000 * 1000,
            'gi' => 1 << 30,
        ];
        if (ctype_digit($maxSize)) {
            return (int) $maxSize;
        }

        if (preg_match('/^(\d++)('.implode('|', array_keys($factors)).')$/i', $maxSize, $matches)) {
            return (((int) $matches[1]) * $factors[strtolower($matches[2])]);
        }

        throw new RuntimeException(sprintf('"%s" is not a valid maximum size.', $maxSize));
    }

    /*
     * Returns the maximum allowed upload size in bytes.
     * This is the minimum value of Part-DB max_file_size, and php.ini's post_max_size and upload_max_filesize.
     */
    public function getMaximumAllowedUploadSize(): int
    {
        if ($this->max_upload_size_bytes) {
            return $this->max_upload_size_bytes;
        }

        $this->max_upload_size_bytes = min(
            $this->parseFileSizeString(ini_get('post_max_size')),
            $this->parseFileSizeString(ini_get('upload_max_filesize')),
            $this->parseFileSizeString($this->max_upload_size),
        );

        return $this->max_upload_size_bytes;
    }
}
