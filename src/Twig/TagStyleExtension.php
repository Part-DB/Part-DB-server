<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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
namespace App\Twig;

use App\Services\Tools\TagStyleResolver;
use Twig\Attribute\AsTwigFunction;

final readonly class TagStyleExtension
{
    public function __construct(private TagStyleResolver $tagStyleResolver)
    {
    }

    /**
     * Returns the badge CSS class the given tag should be rendered with, based on the
     * administrator-configured tag style rules. Falls back to $defaultClass if no rule matches.
     */
    #[AsTwigFunction('tag_badge_class')]
    public function tagBadgeClass(string $tag, string $defaultClass): string
    {
        return $this->tagStyleResolver->resolve($tag, $defaultClass);
    }
}
