<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

declare(strict_types=1);

namespace App\Services\InfoProviderSystem;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Determines how the AI file extractor passes an uploaded file to the AI model. Chosen by the user for every upload,
 * the modes sending the file itself are only available if allowed in the AIFileExtractorSettings.
 */
enum AIFileInputMode: string implements TranslatableInterface
{
    /**
     * The text of the file is extracted and sent to the model. If a file has no text (like images or scanned PDFs),
     * the file itself is sent to the model, so that the model must support images and PDF documents for these.
     */
    case AUTO = 'auto';

    /**
     * Only the extracted text is sent to the model. Works with every model, but not with files without text.
     */
    case TEXT = 'text';

    /**
     * Images and PDF documents are always sent to the model as they are. The model must support them, but it can also
     * read scanned documents, diagrams and the layout of tables. Text files are still sent as text.
     */
    case FILE = 'file';

    /**
     * Whether this mode can send the file itself to the AI model.
     */
    public function sendsFiles(): bool
    {
        return $this !== self::TEXT;
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('info_providers.from_file.input_mode.' . $this->value, locale: $locale);
    }
}
