<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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
 * @package App\Services\LabelSystem
 */
class LabelTextReplacer
{
    protected $providers;

    public function __construct(iterable $providers)
    {
        $this->providers = $providers;
    }

    /**
     * Determine the replacement for a single placeholder. It is iterated over all Replacement Providers.
     * If the given string is not a placeholder or the placeholder is not known, it will be returned unchanged.
     * @param  string  $placeholder The placeholder that should be replaced. (e.g. '%%PLACEHOLDER%%')
     * @param  object  $target The object that should be used for the placeholder info source.
     * @return string  If the placeholder was valid, the replaced info. Otherwise the passed string.
     */
    public function handlePlaceholder(string $placeholder, object $target): string
    {
        foreach ($this->providers as $provider) {
            /** @var PlaceholderProviderInterface $provider */
            $ret = $provider->replace($placeholder, $target);
            if ($ret !== null) {
                return $ret;
            }
        }

        return $placeholder;
    }

    /**
     * Replaces all placeholders in the input lines.
     * @param  string  $lines The input lines that should be replaced
     * @param  object  $target The object that should be used as source for the informations.
     * @return string The Lines with replaced informations.
     */
    public function replace(string $lines, object $target): string
    {
        $patterns = [
            '/(\[\[[A-Z_]+\]\])/' => function ($match) use ($target) {
                return $this->handlePlaceholder($match[0], $target);
            },
        ];

        return preg_replace_callback_array($patterns, $lines);
    }
}