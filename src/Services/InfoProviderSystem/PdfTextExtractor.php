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

use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;

/**
 * Extracts the text layer of PDF documents (like datasheets), so that it can be passed to an AI model.
 * Only text is extracted: scanned documents without a text layer result in an empty string.
 */
final class PdfTextExtractor
{
    /**
     * Extracts the text of the given PDF document, page by page.
     * @param  string  $pdfContent  The binary content of the PDF file
     * @param  int|null  $maxLength  Stop reading further pages, once this many characters were extracted. Null for no limit.
     * @return string The extracted text, with a marker line before each page. Empty if the document has no text layer.
     * @throws \RuntimeException If the document could not be parsed
     */
    public function extractText(string $pdfContent, ?int $maxLength = null): string
    {
        $config = new Config();
        //Images are not needed for the text and only cost memory
        $config->setRetainImageContent(false);

        try {
            $document = (new Parser([], $config))->parseContent($pdfContent);
            $pages = $document->getPages();
        } catch (\Throwable $e) {
            throw new \RuntimeException('The PDF document could not be parsed: '.$e->getMessage(), previous: $e);
        }

        $text = '';
        foreach ($pages as $index => $page) {
            try {
                $pageText = trim($page->getText());
            } catch (\Throwable) {
                //A single broken page should not prevent using the rest of the document
                continue;
            }

            if ($pageText === '') {
                continue;
            }

            $text .= sprintf("--- Page %d ---\n%s\n\n", $index + 1, $pageText);

            //Datasheets can have hundreds of pages, but the relevant information is usually at the beginning
            if ($maxLength !== null && mb_strlen($text) >= $maxLength) {
                break;
            }
        }

        return trim($text);
    }
}
