<?php
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

namespace App\Services;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * This class allows you to convert markdown text to HTML.
 */
class MarkdownParser
{
    protected $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Mark the markdown for rendering.
     * The rendering of markdown is done on client side.
     *
     * @param string $markdown    The markdown text that should be parsed to html.
     * @param bool   $inline_mode Only allow inline markdown codes like (*bold* or **italic**), not something like tables
     *
     * @return string The markdown in a version that can be parsed on client side.
     */
    public function markForRendering(string $markdown, bool $inline_mode = false): string
    {
        return sprintf(
            '<div class="markdown" data-markdown="%s">%s</div>',
            htmlspecialchars($markdown),
            $this->translator->trans('markdown.loading')
        );
    }
}
