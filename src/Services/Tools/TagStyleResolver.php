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

namespace App\Services\Tools;

use App\Settings\TagStyleSettings;

/**
 * Resolves the Bootstrap badge class a tag should be rendered with, based on the
 * administrator-configured list of tag style rules (see TagStyleSettings).
 */
final class TagStyleResolver
{
    public function __construct(private readonly TagStyleSettings $settings)
    {
    }

    /**
     * Resolves the CSS class a tag should be rendered with.
     *
     * At most one rule is applied: an exact rule always wins over a prefix rule, the longest
     * matching prefix wins among prefix rules, and the first configured rule wins ties. If no
     * rule matches (or the winning rule uses the "default" color), $defaultClass is returned
     * completely unchanged.
     *
     * Otherwise, $defaultClass is kept as-is except its color utility class (a "bg-*" or
     * "text-bg-*" token, e.g. "bg-primary") is swapped for the configured color. This preserves
     * any other classes the caller relies on (e.g. Bootstrap's base "badge" class, or a
     * context-specific sizing class like "badge-table") regardless of the color rule applied.
     */
    public function resolve(string $tag, string $defaultClass): string
    {
        $tag = trim($tag);

        $bestColor = null;
        $bestSpecificity = -1;

        foreach ($this->settings->rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $matchType = TagStyleMatchType::tryFrom((string) ($rule['match_type'] ?? ''));
            $pattern = (string) ($rule['pattern'] ?? '');
            $color = TagStyleColor::tryFrom((string) ($rule['color'] ?? ''));

            if ($matchType === null || $color === null || $pattern === '') {
                continue;
            }

            if ($matchType === TagStyleMatchType::EXACT) {
                if ($pattern !== $tag) {
                    continue;
                }
                $specificity = PHP_INT_MAX;
            } else {
                if (!str_starts_with($tag, $pattern)) {
                    continue;
                }
                $specificity = strlen($pattern);
            }

            //Strictly greater, so that on a tie the first configured rule (found first) keeps winning
            if ($specificity > $bestSpecificity) {
                $bestSpecificity = $specificity;
                $bestColor = $color;
            }
        }

        $badgeClass = $bestColor?->toBadgeClass();

        if ($badgeClass === null) {
            return $defaultClass;
        }

        return $this->replaceColorToken($defaultClass, $badgeClass);
    }

    /**
     * Replaces the color utility token (a "bg-*" or "text-bg-*" class) in $classes with
     * $newColorClass, keeping every other class untouched.
     */
    private function replaceColorToken(string $classes, string $newColorClass): string
    {
        $tokens = preg_split('/\s+/', trim($classes), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = array_values(array_filter(
            $tokens,
            static fn (string $token): bool => !preg_match('/^(text-)?bg-/', $token)
        ));
        $tokens[] = $newColorClass;

        return implode(' ', $tokens);
    }
}
