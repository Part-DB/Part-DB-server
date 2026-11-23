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

use App\Entity\Parts\Tag;
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
     * @param mixed $value The tags of the part
     * @return Tag[]
     */
    public function normalize(mixed $value): mixed
    {
        if (!is_iterable($value)) {
            return [];
        }

        $tags = is_array($value) ? $value : iterator_to_array($value, false);
        usort($tags, static fn(Tag $a, Tag $b): int => strnatcasecmp($a->getName(), $b->getName()));

        return $tags;
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
                '<a href="%s" class="badge %s badge-table">%s</a>',
                $this->urlGenerator->generate('part_list_tags', ['tag' => $tag->getName()]),
                $tag->getBadgeClass(),
                htmlspecialchars($tag->getName())
            );
        }

        return $html;
    }
}
