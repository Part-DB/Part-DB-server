<?php
/**
 *
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/
 *
 * part-db version 0.2+
 * Copyright (C) 2009 K. Jacobs and others (see authors.php)
 * http://code.google.com/p/part-db/
 *
 * Part-DB Version 0.4+
 * Copyright (C) 2016 - 2019 Jan Böhmer
 * https://github.com/jbtronics
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
 *
 */

namespace App\Services;


use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * This class allows you to convert markdown text to HTML.
 * @package App\Services
 */
class MarkdownParser
{
    protected $cache;
    /** @var \Parsedown */
    protected $parsedown;

    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
        $this->initParsedown();
    }

    protected function initParsedown()
    {
        $this->parsedown = new \Parsedown();
        $this->parsedown->setSafeMode(true);
    }

    /**
     * Converts the given markdown text to HTML.
     * The result is cached.
     * @param string $markdown The markdown text that should be parsed to html.
     * @param bool $inline_mode Only allow inline markdown codes like (*bold* or **italic**), not something like tables
     * @return string The HTML version of the given text.
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function parse(string $markdown, bool $inline_mode = false) : string
    {
        //Generate key
        if ($inline_mode) {
            $key = 'markdown_i_' . md5($markdown);
        } else {
            $key = 'markdown_' . md5($markdown);
        }
        return $this->cache->get($key, function (ItemInterface $item) use ($markdown, $inline_mode) {
            //Expire text after 2 months
            $item->expiresAfter(311040000);

            if ($inline_mode) {
                return $this->parsedown->line($markdown);
            }

            return '<div class="markdown">' . $this->parsedown->text($markdown) . '</div>';
        });
    }
}