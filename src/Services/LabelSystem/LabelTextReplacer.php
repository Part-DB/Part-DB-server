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

class LabelTextReplacer
{
    protected $providers;

    public function __construct(iterable $providers)
    {
        $this->providers = $providers;
    }

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

    public function replace(string $lines, object $target): string
    {
        $patterns = [
            '/(%%.*%%)/' => function ($match) use ($target) {
                return $this->handlePlaceholder($match[0], $target);
            },
        ];

        return preg_replace_callback_array($patterns, $lines);
    }
}