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

namespace App\DataTables\Column;

use Omines\DataTablesBundle\Column\AbstractColumn;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TagsColumn extends AbstractColumn
{
    public function __construct(protected UrlGeneratorInterface $urlGenerator)
    {
    }

    /**
     * The normalize function is responsible for converting parsed and processed data to a datatables-appropriate type.
     *
     * @param mixed $value The single value of the column
     * @return mixed
     */
    public function normalize(mixed $value): mixed
    {
        if (empty($value)) {
            return [];
        }

        return explode(',', (string) $value);
    }

    public function render($tags, $context): string
    {
        if (!is_iterable($tags)) {
            throw new \LogicException('TagsColumn::render() expects an iterable');
        }

        $html = '';
        $count = 10;
        foreach ($tags as $tag) {
            //Only show max 10 tags
            if (--$count < 0) {
                break;
            }
            $html .= sprintf(
                '<a href="%s" class="badge bg-primary badge-table">%s</a>',
                $this->urlGenerator->generate('part_list_tags', ['tag' => $tag]),
                htmlspecialchars((string) $tag)
            );
        }

        return $html;
    }
}
