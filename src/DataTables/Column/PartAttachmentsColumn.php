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

namespace App\DataTables\Column;

use App\Entity\Attachments\Attachment;
use App\Entity\Parts\Part;
use App\Services\Attachments\AttachmentManager;
use App\Services\EntityURLGenerator;
use App\Services\Misc\FAIconGenerator;
use Omines\DataTablesBundle\Column\AbstractColumn;
use RuntimeException;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PartAttachmentsColumn extends AbstractColumn
{
    public function __construct(protected FAIconGenerator $FAIconGenerator, protected EntityURLGenerator $urlGenerator, protected AttachmentManager $attachmentManager)
    {
    }

    /**
     * The normalize function is responsible for converting parsed and processed data to a datatables-appropriate type.
     *
     * @param mixed $value The single value of the column
     * @return mixed
     */
    public function normalize($value): mixed
    {
        return $value;
    }

    public function render($value, $context): string
    {
        if (!$context instanceof Part) {
            throw new RuntimeException('$context must be a Part object!');
        }
        $tmp = '';
        $attachments = $context->getAttachments()->filter(fn(Attachment $attachment) => $attachment->getShowInTable() && $this->attachmentManager->isFileExisting($attachment));

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
                htmlspecialchars($attachment->getName()).': '.htmlspecialchars($attachment->getFilename() ?? $attachment->getHost() ?? ''),
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

    public function configureOptions(OptionsResolver $resolver): static
    {
        parent::configureOptions($resolver);

        return $this;
    }
}
