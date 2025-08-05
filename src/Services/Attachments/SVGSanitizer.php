<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2025 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Services\Attachments;

use Rhukster\DomSanitizer\DOMSanitizer;

class SVGSanitizer
{

    /**
     * Sanitizes the given SVG string by removing any potentially harmful content (like inline scripts).
     * @param  string  $input
     * @return string
     */
    public function sanitizeString(string $input): string
    {
        return (new DOMSanitizer(DOMSanitizer::SVG))->sanitize($input);
    }

    /**
     * Sanitizes the given SVG file by removing any potentially harmful content (like inline scripts).
     * The sanitized content is written back to the file.
     * @param  string  $filepath
     */
    public function sanitizeFile(string $filepath): void
    {
        //Open the file and read the content
        $content = file_get_contents($filepath);
        if ($content === false) {
            throw new \RuntimeException('Could not read file: ' . $filepath);
        }
        //Sanitize the content
        $sanitizedContent = $this->sanitizeString($content);
        //Write the sanitized content back to the file
        file_put_contents($filepath, $sanitizedContent);
    }
}