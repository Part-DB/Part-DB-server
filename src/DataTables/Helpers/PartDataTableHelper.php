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

namespace App\DataTables\Helpers;

use App\Entity\Parts\Part;
use App\Services\Attachments\AttachmentURLGenerator;
use App\Services\Attachments\PartPreviewGenerator;
use App\Services\EntityURLGenerator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A helper service which contains common code to render columns for part related tables
 */
class PartDataTableHelper
{
    private PartPreviewGenerator $previewGenerator;
    private AttachmentURLGenerator $attachmentURLGenerator;

    private TranslatorInterface $translator;
    private EntityURLGenerator $entityURLGenerator;

    public function __construct(PartPreviewGenerator $previewGenerator, AttachmentURLGenerator $attachmentURLGenerator,
    EntityURLGenerator $entityURLGenerator, TranslatorInterface $translator)
    {
        $this->previewGenerator = $previewGenerator;
        $this->attachmentURLGenerator = $attachmentURLGenerator;
        $this->translator = $translator;
        $this->entityURLGenerator = $entityURLGenerator;
    }

    public function renderName(Part $context): string
    {
        $icon = '';

        //Depending on the part status we show a different icon (the later conditions have higher priority)
        if ($context->isFavorite()) {
            $icon = sprintf('<i class="fa-solid fa-star fa-fw me-1" title="%s"></i>', $this->translator->trans('part.favorite.badge'));
        }
        if ($context->isNeedsReview()) {
            $icon = sprintf('<i class="fa-solid fa-ambulance fa-fw me-1" title="%s"></i>', $this->translator->trans('part.needs_review.badge'));
        }
        if ($context->getBuiltProject() !== null) {
            $icon = sprintf('<i class="fa-solid fa-box-archive fa-fw me-1" title="%s"></i>',
                $this->translator->trans('part.info.projectBuildPart.hint') . ': ' . $context->getBuiltProject()->getName());
        }


        return sprintf(
            '<a href="%s">%s%s</a>',
            $this->entityURLGenerator->infoURL($context),
            $icon,
            htmlspecialchars($context->getName())
        );
    }

    public function renderPicture(Part $context): string
    {
        $preview_attachment = $this->previewGenerator->getTablePreviewAttachment($context);
        if (null === $preview_attachment) {
            return '';
        }

        $title = htmlspecialchars($preview_attachment->getName());
        if ($preview_attachment->getFilename()) {
            $title .= ' ('.htmlspecialchars($preview_attachment->getFilename()).')';
        }

        return sprintf(
            '<img alt="%s" src="%s" data-thumbnail="%s" class="%s" data-title="%s" data-controller="elements--hoverpic">',
            'Part image',
            $this->attachmentURLGenerator->getThumbnailURL($preview_attachment),
            $this->attachmentURLGenerator->getThumbnailURL($preview_attachment, 'thumbnail_md'),
            'hoverpic part-table-image',
            $title
        );
    }
}