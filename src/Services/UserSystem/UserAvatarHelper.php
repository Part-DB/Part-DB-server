<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Services\UserSystem;

use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\AttachmentType;
use App\Entity\Attachments\AttachmentUpload;
use App\Entity\Attachments\UserAttachment;
use App\Entity\UserSystem\User;
use App\Services\Attachments\AttachmentSubmitHandler;
use App\Services\Attachments\AttachmentURLGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class UserAvatarHelper
{
    /**
     * Path to the default avatar image (must not start with a slash, or the asset package will not work)
     */
    public const IMG_DEFAULT_AVATAR_PATH = 'img/default_avatar.svg';

    public function __construct(
        private readonly bool $use_gravatar,
        private readonly Packages $packages,
        private readonly AttachmentURLGenerator $attachmentURLGenerator,
        private readonly EntityManagerInterface $entityManager,
        private readonly AttachmentSubmitHandler $submitHandler
    ) {
    }


    /**
     *  Returns the URL to the profile picture of the given user (in big size)
     *
     * @return string
     */
    public function getAvatarURL(User $user): string
    {
        //Check if the user has a master attachment defined (meaning he has explicitly defined a profile picture)
        if ($user->getMasterPictureAttachment() instanceof Attachment) {
            return $this->attachmentURLGenerator->getThumbnailURL($user->getMasterPictureAttachment(), 'thumbnail_md')
                ?? $this->packages->getUrl(self::IMG_DEFAULT_AVATAR_PATH);
        }

        //If not check if gravatar is enabled (then use gravatar URL)
        if ($this->use_gravatar) {
            return $this->getGravatar($user, 200); //200px wide picture
        }

        //Fallback to the default avatar picture
        return $this->packages->getUrl(self::IMG_DEFAULT_AVATAR_PATH);
    }

    public function getAvatarSmURL(User $user): string
    {
        //Check if the user has a master attachment defined (meaning he has explicitly defined a profile picture)
        if ($user->getMasterPictureAttachment() instanceof Attachment) {
            return $this->attachmentURLGenerator->getThumbnailURL($user->getMasterPictureAttachment(), 'thumbnail_xs')
                ?? $this->packages->getUrl(self::IMG_DEFAULT_AVATAR_PATH);
        }

        //If not check if gravatar is enabled (then use gravatar URL)
        if ($this->use_gravatar) {
            return $this->getGravatar($user, 50); //50px wide picture
        }

        //Otherwise serve the default image (its an SVG, so we dont need to thumbnail it)
        return $this->packages->getUrl(self::IMG_DEFAULT_AVATAR_PATH);
    }

    public function getAvatarMdURL(User $user): string
    {
        //Check if the user has a master attachment defined (meaning he has explicitly defined a profile picture)
        if ($user->getMasterPictureAttachment() instanceof Attachment) {
            return $this->attachmentURLGenerator->getThumbnailURL($user->getMasterPictureAttachment(), 'thumbnail_sm')
                ?? $this->packages->getUrl(self::IMG_DEFAULT_AVATAR_PATH);
        }

        //If not check if gravatar is enabled (then use gravatar URL)
        if ($this->use_gravatar) {
            return $this->getGravatar($user, 150);
        }

        //Otherwise serve the default image (its an SVG, so we dont need to thumbnail it)
        return $this->packages->getUrl(self::IMG_DEFAULT_AVATAR_PATH);
    }


    /**
     * Get either a Gravatar URL or complete image tag for a specified email address.
     *
     * @param  User  $user  The user for which the gravator should be generated
     * @param  int  $s  Size in pixels, defaults to 80px [ 1 - 2048 ]
     * @param  string  $d  Default imageset to use [ 404 | mm | identicon | monsterid | wavatar ]
     * @param  string  $r  Maximum rating (inclusive) [ g | pg | r | x ]
     *
     * @return string containing either just a URL or a complete image tag
     * @source https://gravatar.com/site/implement/images/php/
     */
    private function getGravatar(User $user, int $s = 80, string $d = 'identicon', string $r = 'g'): string
    {
        $email = $user->getEmail();
        if ($email === null || $email === '') {
            $email = 'Part-DB';
        }

        $url = 'https://www.gravatar.com/avatar/';
        $url .= md5(strtolower(trim($email)));

        return $url."?s=$s&d=$d&r=$r";
    }

    /**
     * Handles the upload of the user avatar.
     */
    public function handleAvatarUpload(User $user, UploadedFile $file): Attachment
    {
        //Determine which attachment to user
        //If the user already has a master attachment, we use this one
        if ($user->getMasterPictureAttachment() instanceof Attachment) {
            $attachment = $user->getMasterPictureAttachment();
        } else { //Otherwise we have to create one
            $attachment = new UserAttachment();
            $user->addAttachment($attachment);
            $user->setMasterPictureAttachment($attachment);
            $attachment->setName('Avatar');

            //Retrieve or create the avatar attachment type
            $attachment_type = $this->entityManager->getRepository(AttachmentType::class)->findOneBy(['name' => 'Avatars']);
            if ($attachment_type === null) {
                $attachment_type = new AttachmentType();
                $attachment_type->setName('Avatars');
                $attachment_type->setFiletypeFilter('image/*');
                $this->entityManager->persist($attachment_type);
            }

            $attachment->setAttachmentType($attachment_type);
        }

        //Handle the upload
        $this->submitHandler->handleUpload($attachment, new AttachmentUpload(file: $file));

        //Set attachment as master picture
        $user->setMasterPictureAttachment($attachment);

        return $attachment;
    }
}
