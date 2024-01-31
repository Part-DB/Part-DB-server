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

namespace App\DataTables\Helpers;

use App\Entity\Parts\StorageLocation;
use App\Entity\ProjectSystem\Project;
use App\Entity\Attachments\Attachment;
use App\Entity\Parts\Part;
use App\Services\Attachments\AttachmentURLGenerator;
use App\Services\Attachments\PartPreviewGenerator;
use App\Services\EntityURLGenerator;
use App\Services\Formatters\AmountFormatter;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A helper service which contains common code to render columns for part related tables
 */
class PartDataTableHelper
{
    public function __construct(
        private readonly PartPreviewGenerator $previewGenerator,
        private readonly AttachmentURLGenerator $attachmentURLGenerator,
        private readonly EntityURLGenerator $entityURLGenerator,
        private readonly TranslatorInterface $translator,
        private readonly AmountFormatter $amountFormatter,
    ) {
    }

    public function renderName(Part $context): string
    {
        $icon = '';

        //Depending on the part status we show a different icon (the later conditions have higher priority)
        if ($context->isFavorite()) {
            $icon = sprintf('<i class="fa-solid fa-star fa-fw me-1" title="%s"></i>',
                $this->translator->trans('part.favorite.badge'));
        }
        if ($context->isNeedsReview()) {
            $icon = sprintf('<i class="fa-solid fa-ambulance fa-fw me-1" title="%s"></i>',
                $this->translator->trans('part.needs_review.badge'));
        }
        if ($context->getBuiltProject() instanceof Project) {
            $icon = sprintf('<i class="fa-solid fa-box-archive fa-fw me-1" title="%s"></i>',
                $this->translator->trans('part.info.projectBuildPart.hint').': '.$context->getBuiltProject()->getName());
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
        if (!$preview_attachment instanceof Attachment) {
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

    public function renderStorageLocations(Part $context): string
    {
        $tmp = [];
        foreach ($context->getPartLots() as $lot) {
            //Ignore lots without storelocation
            if (!$lot->getStorageLocation() instanceof StorageLocation) {
                continue;
            }
            $tmp[] = sprintf(
                '<a href="%s" title="%s">%s</a>',
                $this->entityURLGenerator->listPartsURL($lot->getStorageLocation()),
                htmlspecialchars($lot->getStorageLocation()->getFullPath()),
                htmlspecialchars($lot->getStorageLocation()->getName())
            );
        }

        return implode('<br>', $tmp);
    }

    public function renderAmount(Part $context): string
    {
        $amount = $context->getAmountSum();
        $expiredAmount = $context->getExpiredAmountSum();

        $ret = '';

        if ($context->isAmountUnknown()) {
            //When all amounts are unknown, we show a question mark
            if ($amount === 0.0) {
                $ret .= sprintf('<b class="text-primary" title="%s">?</b>',
                    $this->translator->trans('part_lots.instock_unknown'));
            } else { //Otherwise mark it with greater equal and the (known) amount
                $ret .= sprintf('<b class="text-primary" title="%s">≥</b>',
                    $this->translator->trans('part_lots.instock_unknown')
                );
                $ret .= htmlspecialchars($this->amountFormatter->format($amount, $context->getPartUnit()));
            }
        } else {
            $ret .= htmlspecialchars($this->amountFormatter->format($amount, $context->getPartUnit()));
        }

        //If we have expired lots, we show them in parentheses behind
        if ($expiredAmount > 0) {
            $ret .= sprintf(' <span title="%s" class="text-muted">(+%s)</span>',
                $this->translator->trans('part_lots.is_expired'),
                htmlspecialchars($this->amountFormatter->format($expiredAmount, $context->getPartUnit())));
        }

        //When the amount is below the minimum amount, we highlight the number red
        if ($context->isNotEnoughInstock()) {
            $ret = sprintf('<b class="text-danger" title="%s">%s</b>',
                $this->translator->trans('part.info.amount.less_than_desired'),
                $ret);
        }

        return $ret;
    }
}
