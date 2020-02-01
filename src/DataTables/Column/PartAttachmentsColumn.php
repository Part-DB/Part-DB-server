<?php

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
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
 */

namespace App\DataTables\Column;

use App\Entity\Attachments\Attachment;
use App\Entity\Parts\Part;
use App\Services\Attachments\AttachmentManager;
use App\Services\EntityURLGenerator;
use App\Services\FAIconGenerator;
use Omines\DataTablesBundle\Column\AbstractColumn;
use RuntimeException;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PartAttachmentsColumn extends AbstractColumn
{
    protected $FAIconGenerator;
    protected $urlGenerator;
    protected $attachmentManager;

    public function __construct(FAIconGenerator $FAIconGenerator, EntityURLGenerator $urlGenerator, AttachmentManager $attachmentManager)
    {
        $this->FAIconGenerator = $FAIconGenerator;
        $this->urlGenerator = $urlGenerator;
        $this->attachmentManager = $attachmentManager;
    }

    /**
     * The normalize function is responsible for converting parsed and processed data to a datatables-appropriate type.
     *
     * @param mixed $value The single value of the column
     *
     * @return mixed
     */
    public function normalize($value)
    {
        return $value;
    }

    public function render($value, $context)
    {
        if (! $context instanceof Part) {
            throw new RuntimeException('$context must be a Part object!');
        }
        $tmp = '';
        $attachments = $context->getAttachments()->filter(function (Attachment $attachment) {
            return $attachment->getShowInTable() && $this->attachmentManager->isFileExisting($attachment);
        });

        $count = 5;
        foreach ($attachments as $attachment) {
            //Only show the first 5 attachments
            if (--$count < 0) {
                break;
            }
            /** @var Attachment $attachment */
            $tmp .= sprintf(
                '<a href="%s" title="%s" class="attach-table-icon" target="_blank" rel="noopener" data-no-ajax>%s</a>',
                $this->urlGenerator->viewURL($attachment),
                htmlspecialchars($attachment->getName()).': '.htmlspecialchars($attachment->getFilename()),
                $this->FAIconGenerator->generateIconHTML(
                    // Sometimes the extension can not be determined, so ensure a generic icon is shown
                    $this->FAIconGenerator->fileExtensionToFAType($attachment->getExtension() ?? 'file'),
                    'fas',
                    'fa-2x'
                )
            );
        }

        return $tmp;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);
        return $this;
    }
}
