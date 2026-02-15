<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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
namespace App\Twig;

use App\Entity\Attachments\Attachment;
use App\Services\Attachments\AttachmentURLGenerator;
use App\Services\Misc\FAIconGenerator;
use Twig\Attribute\AsTwigFunction;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final readonly class AttachmentExtension
{
    public function __construct(private AttachmentURLGenerator $attachmentURLGenerator, private FAIconGenerator $FAIconGenerator)
    {
    }

    /**
     * Returns the URL of the thumbnail of the given attachment. Returns null if no thumbnail is available.
     */
    #[AsTwigFunction("attachment_thumbnail")]
    public function attachmentThumbnail(Attachment $attachment, string $filter_name = 'thumbnail_sm'): ?string
    {
        return $this->attachmentURLGenerator->getThumbnailURL($attachment, $filter_name);
    }

    /**
     * Return the font-awesome icon type for the given file extension. Returns "file" if no specific icon is available.
     * Null is allowed for files withot extension
     * @param  string|null  $extension
     * @return string
     */
    #[AsTwigFunction("ext_to_fa_icon")]
    public function extToFAIcon(?string $extension): string
    {
        return $this->FAIconGenerator->fileExtensionToFAType($extension ?? '');
    }
}
