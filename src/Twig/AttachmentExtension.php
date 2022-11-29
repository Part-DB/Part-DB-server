<?php
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

use App\Services\Attachments\AttachmentURLGenerator;
use App\Services\FAIconGenerator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AttachmentExtension extends AbstractExtension
{
    protected AttachmentURLGenerator $attachmentURLGenerator;
    protected FAIconGenerator $FAIconGenerator;

    public function __construct(AttachmentURLGenerator $attachmentURLGenerator, FAIconGenerator $FAIconGenerator)
    {
        $this->attachmentURLGenerator = $attachmentURLGenerator;
        $this->FAIconGenerator = $FAIconGenerator;
    }

    public function getFunctions(): array
    {
        return [
            /* Returns the URL to a thumbnail of the given attachment */
            new TwigFunction('attachment_thumbnail', [$this->attachmentURLGenerator, 'getThumbnailURL']),
            /* Returns the font awesome icon class which is representing the given file extension  */
            new TwigFunction('ext_to_fa_icon', [$this->FAIconGenerator, 'fileExtensionToFAType']),
        ];
    }
}