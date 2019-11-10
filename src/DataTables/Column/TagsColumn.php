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

namespace App\DataTables\Column;


use Omines\DataTablesBundle\Column\AbstractColumn;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TagsColumn extends AbstractColumn
{

    protected $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * The normalize function is responsible for converting parsed and processed data to a datatables-appropriate type.
     *
     * @param mixed $value The single value of the column
     * @return mixed
     */
    public function normalize($value)
    {
        if (empty($value)) {
            return [];
        }
        return explode(',', $value);
    }

    public function render($tags, $context)
    {
        $html = '';
        $count = 10;
        foreach ($tags as $tag) {
            //Only show max 10 tags
            if (--$count < 0) {
                break;
            }
            $html .= sprintf(
                '<a href="%s" class="badge badge-primary badge-table">%s</a>',
                $this->urlGenerator->generate('part_list_tags', ['tag' => $tag]),
                htmlspecialchars($tag)
            );
        }

        return $html;
    }
}