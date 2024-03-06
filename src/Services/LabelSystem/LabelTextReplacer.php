<?php
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

declare(strict_types=1);

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

namespace App\Services\LabelSystem;

use App\Services\LabelSystem\PlaceholderProviders\PlaceholderProviderInterface;

/**
 * This service replaces the Placeholders of the user provided lines with the proper informations.
 * It uses the PlaceholderProviders provided by PlaceholderProviderInterface classes.
 * @see \App\Tests\Services\LabelSystem\LabelTextReplacerTest
 */
final class LabelTextReplacer
{
    public function __construct(private readonly iterable $providers)
    {
    }

    /**
     * Determine the replacement for a single placeholder. It is iterated over all Replacement Providers.
     * If the given string is not a placeholder or the placeholder is not known, it will be returned unchanged.
     *
     * @param string $placeholder The placeholder that should be replaced. (e.g. '%%PLACEHOLDER%%')
     * @param object $target      the object that should be used for the placeholder info source
     *
     * @return string If the placeholder was valid, the replaced info. Otherwise the passed string.
     */
    public function handlePlaceholder(string $placeholder, object $target): string
    {
        return $this->handlePlaceholderOrReturnNull($placeholder, $target) ?? $placeholder;
    }

    /**
     * Similar to handlePlaceholder, but returns null if the placeholder is not known (instead of the original string)
     * @param  string  $placeholder
     * @param  object  $target
     * @return string|null
     */
    public function handlePlaceholderOrReturnNull(string $placeholder, object $target): ?string
    {
        foreach ($this->providers as $provider) {
            /** @var PlaceholderProviderInterface $provider */
            $ret = $provider->replace($placeholder, $target);
            if (null !== $ret) {
                return $ret;
            }
        }

        return null;
    }

    /**
     *  Replaces all placeholders in the input lines.
     *
     * @param string $lines  The input lines that should be replaced
     * @param object $target the object that should be used as source for the information
     *
     * @return string the Lines with replaced information
     */
    public function replace(string $lines, object $target): string
    {
        $patterns = [
            '/(\[\[[A-Z_0-9]+\]\])/' => fn($match): string => $this->handlePlaceholder($match[0], $target),
        ];

        return preg_replace_callback_array($patterns, $lines) ?? throw new \RuntimeException('Could not replace placeholders!');

    }
}
