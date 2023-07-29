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

namespace App\Services\Misc;

use App\Entity\Attachments\Attachment;
use function in_array;
use InvalidArgumentException;

/**
 * @see \App\Tests\Services\Misc\FAIconGeneratorTest
 */
class FAIconGenerator
{
    protected const EXT_MAPPING = [
        'fa-file-pdf' => ['pdf', 'ps', 'eps'],
        'fa-file-image' => Attachment::PICTURE_EXTS,
        'fa-file-lines' => ['txt', 'md', 'log', 'rst', 'tex'],
        'fa-file-csv' => ['csv', 'tsv'],
        'fa-file-word' => ['doc', 'docx', 'odt', 'rtf'],
        'fa-file-zipper' => ['zip', 'rar', 'bz2', 'tar', '7z', 'gz', 'tgz', 'xz', 'txz', 'tbz'],
        'fa-file-audio' => ['mp3', 'wav', 'aac', 'm4a', 'wma', 'ogg', 'flac', 'alac'],
        'fa-file-powerpoint' => ['ppt', 'pptx', 'odp', 'pps', 'key'],
        'fa-file-excel' => ['xls', 'xlr', 'xlsx', 'ods', 'numbers'],
        'fa-file-code' => ['php', 'xml', 'html', 'js', 'ts', 'htm', 'c', 'cpp', 'json', 'py', 'css', 'yml', 'yaml',
            'sql', 'sh', 'bat', 'exe', 'dll', 'lib', 'so', 'a', 'o', 'h', 'hpp', 'java', 'class', 'jar', 'rb', 'rbw', 'rake', 'gem',],
        'fa-file-video' => ['webm', 'avi', 'mp4', 'mkv', 'wmv'],
    ];

    /**
     * Gets the Font awesome icon class for a file with the specified extension.
     * For example 'pdf' gives you 'fa-file-pdf'.
     *
     * @param string $extension The file extension (without dot). Must be ASCII chars only!
     *
     * @return string The fontawesome class with leading 'fa-'
     */
    public function fileExtensionToFAType(string $extension): string
    {
        //Normalize file extension
        $extension = strtolower($extension);
        foreach (self::EXT_MAPPING as $fa => $exts) {
            if (in_array($extension, $exts, true)) {
                return $fa;
            }
        }

        // When the extension is not found in the mapping array, we return the generic icon
        return 'fa-file';
    }

    /**
     * Returns HTML code to show the given fontawesome icon.
     * E.g. <i class="fas fa-file-text"></i>.
     *
     * @param string $icon_class The icon which should be shown (e.g. fa-file-text)
     * @param string $style      The style of the icon 'fas'
     * @param string $options    any other css class attributes like size, etc
     *
     * @return string The final html
     */
    public function generateIconHTML(string $icon_class, string $style = 'fa-solid', string $options = ''): string
    {
        //XSS protection
        $icon_class = htmlspecialchars($icon_class);
        $style = htmlspecialchars($style);
        $options = htmlspecialchars($options);

        return sprintf(
            '<i class="%s %s %s"></i>',
            $style,
            $icon_class,
            $options
        );
    }
}
