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
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Extracts the text of an uploaded file.
 * Currently supported are Markdown, text and PDF files.
 */
final class FileContentExtractor
{
    public const PDF_MIME_TYPES = [
        'application/pdf',
        'application/x-pdf',
    ];

    /** @var string[] The files, whose text can be extracted */
    public const ALLOWED_MIME_TYPES = [
        ...self::PDF_MIME_TYPES,
        'text/plain',
        'text/markdown',
    ];

    /** @var string[] Images, which have no text to extract, but can be sent to AI models supporting images. These are the formats supported by the common AI APIs. */
    public const IMAGE_MIME_TYPES = [
        'image/png',
        'image/jpeg',
        'image/webp',
        'image/gif',
    ];

    public function isPdf(?string $mimeType): bool
    {
        return in_array($mimeType, self::PDF_MIME_TYPES, true);
    }

    public function isImage(?string $mimeType): bool
    {
        return in_array($mimeType, self::IMAGE_MIME_TYPES, true);
    }

    /**
     * Extracts the text of the given file. The type of the file is determined by its content, not its extension.
     * @param  File  $file  The file to extract the text from
     * @param  int|null  $maxLength  The number of characters after which the extraction may stop. Null for no limit.
     * @return string The extracted text, as UTF-8. Empty if the file contains no text.
     * @throws \RuntimeException If the file type is not supported or the file could not be read
     */
    public function extractContent(File $file, ?int $maxLength = null): string
    {
        $mimeType = $file->getMimeType() ?? throw new \RuntimeException('Unsupported file type: unknown');

        if ($this->isPdf($mimeType)) {
            return $this->extractPDFText($file->getContent(), $maxLength);
        }

        if (str_starts_with($mimeType, 'text/')) {
            return $this->extractPlainText($file->getContent(), $maxLength);
        }

        throw new \RuntimeException('Unsupported file type: '.$mimeType);
    }

    /**
     * Prepares the content of a text file (like plain text or Markdown) to be passed on.
     * @param  string  $content  The raw content of the file
     * @param  int|null  $maxLength  The text is cut off after this many characters. Null for no limit.
     * @return string The text as UTF-8, without surrounding whitespace and byte order mark
     */
    public function extractPlainText(string $content, ?int $maxLength = null): string
    {
        //The text ends up in a JSON request to the AI platform, which requires valid UTF-8. Files which are not, are
        //most likely in the legacy encoding of Windows (a superset of ISO-8859-1), which is used by many older tools.
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $text = $this->trim($content);

        if ($maxLength !== null) {
            $text = mb_substr($text, 0, $maxLength);
        }

        return $text;
    }

    /**
     * Extracts the text of the given PDF document, page by page.
     * @param  string  $pdfContent  The binary content of the PDF file
     * @param  int|null  $maxLength  Stop reading further pages, once this many characters were extracted. Null for no limit.
     * @return string The extracted text, with a marker line before each page. Empty if the document has no text layer.
     * @throws \RuntimeException If the document could not be parsed
     */
    public function extractPDFText(string $pdfContent, ?int $maxLength = null): string
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
                $pageText = $this->trim($page->getText());
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

    /**
     * Like trim(), but also removes non-breaking spaces and byte order marks, which trim() does not know.
     */
    private function trim(string $text): string
    {
        //preg_replace() fails (returns null) on invalid UTF-8, then at least the ASCII whitespace is removed
        return preg_replace('/^[\s\x{00A0}\x{FEFF}]+|[\s\x{00A0}\x{FEFF}]+$/u', '', $text) ?? trim($text);
    }
}
