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

namespace App\Services\Formatters;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * This class allows you to convert Markdown text to HTML.
 */
class MarkdownParser
{
    protected TranslatorInterface $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Mark the markdown for rendering.
     * The rendering of markdown is done on client side.
     *
     * @param string $markdown    the Markdown text that should be parsed to html
     * @param bool   $inline_mode When true, p blocks will have no margins behind them
     *
     * @return string the markdown in a version that can be parsed on client side
     */
    public function markForRendering(string $markdown, bool $inline_mode = false): string
    {
        return sprintf(
            '<div class="markdown %s" data-markdown="%s" data-controller="common--markdown">%s</div>',
            $inline_mode ? 'markdown-inline' : '',  //Add class if inline mode is enabled, to prevent margin after p
            htmlspecialchars($markdown),
            $this->translator->trans('markdown.loading')
        );
    }
}
