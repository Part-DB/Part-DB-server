<?php
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

use App\Entity\UserSystem\User;
use App\Services\Attachments\AttachmentURLGenerator;
use Liip\ImagineBundle\Service\FilterService;
use Symfony\Component\Asset\Packages;

class UserAvatarHelper
{
    private bool $use_gravatar;
    private Packages $packages;
    private AttachmentURLGenerator $attachmentURLGenerator;
    private FilterService $filterService;

    public function __construct(bool $use_gravatar, Packages $packages, AttachmentURLGenerator $attachmentURLGenerator, FilterService $filterService)
    {
        $this->use_gravatar = $use_gravatar;
        $this->packages = $packages;
        $this->attachmentURLGenerator = $attachmentURLGenerator;
        $this->filterService = $filterService;
    }


    /**
     * Returns the URL to the profile picture of the given user (in big size)
     * @param  User  $user
     * @return string
     */
    public function getAvatarURL(User $user): string
    {
        //Check if the user has a master attachment defined (meaning he has explicitly defined a profile picture)
        if ($user->getMasterPictureAttachment() !== null) {
            return $this->attachmentURLGenerator->getThumbnailURL($user->getMasterPictureAttachment(), 'thumbnail_md');
        }

        //If not check if gravatar is enabled (then use gravatar URL)
        if ($this->use_gravatar) {
            return $this->getGravatar($user, 200); //200px wide picture
        }

        //Fallback to the default avatar picture
        return $this->packages->getUrl('/img/default_avatar.png');
    }

    public function getAvatarSmURL(User $user): string
    {
        //Check if the user has a master attachment defined (meaning he has explicitly defined a profile picture)
        if ($user->getMasterPictureAttachment() !== null) {
            return $this->attachmentURLGenerator->getThumbnailURL($user->getMasterPictureAttachment(), 'thumbnail_xs');
        }

        //If not check if gravatar is enabled (then use gravatar URL)
        if ($this->use_gravatar) {
            return $this->getGravatar($user, 50); //50px wide picture
        }

        try {
            //Otherwise we can serve the relative path via Asset component
            return $this->filterService->getUrlOfFilteredImage('/img/default_avatar.png', 'thumbnail_xs');
        } catch (\Imagine\Exception\RuntimeException $e) {
            //If the filter fails, we can not serve the thumbnail and fall back to the original image and log an warning
            return $this->packages->getUrl('/img/default_avatar.png');
        }
    }


    /**
     * Get either a Gravatar URL or complete image tag for a specified email address.
     *
     * @param  User $user The user for which the gravator should be generated
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
        if (empty($email)) {
            $email = 'Part-DB';
        }

        $url = 'https://www.gravatar.com/avatar/';
        $url .= md5(strtolower(trim($email)));
        $url .= "?s=${s}&d=${d}&r=${r}";

        return $url;
    }
}