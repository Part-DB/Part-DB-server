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

namespace App\Helpers;

use League\HTMLToMarkdown\HtmlConverter;
use s9e\TextFormatter\Bundles\Forum as TextFormatter;

/**
 * @see \App\Tests\Helpers\BBCodeToMarkdownConverterTest
 */
class BBCodeToMarkdownConverter
{
    protected HtmlConverter $html_to_markdown;

    public function __construct()
    {
        $this->html_to_markdown = new HtmlConverter();
    }

    /**
     * Converts the given BBCode to markdown.
     * BBCode tags that does not have a markdown aequivalent are outputed as HTML tags.
     *
     * @param string $bbcode The Markdown that should be converted
     *
     * @return string the markdown version of the text
     */
    public function convert(string $bbcode): string
    {
        //Convert BBCode to html
        $xml = TextFormatter::parse($bbcode);
        $html = TextFormatter::render($xml);

        //Now convert the HTML to markdown
        return $this->html_to_markdown->convert($html);
    }
}
