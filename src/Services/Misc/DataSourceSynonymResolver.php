<?php

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Services\Misc;

use App\Settings\BehaviorSettings\DataSourceSynonymsSettings;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class DataSourceSynonymResolver
{
    public function __construct(
        private TranslatorInterface        $translator,
        private DataSourceSynonymsSettings $synonymsSettings,
    ) {
    }

    public function displayNamePlural(string $dataSource, string $defaultKey, ?string $locale = null): string
    {
        $locale ??= $this->translator->getLocale();
        $syn = $this->synonyms($dataSource, $locale);

        if ($syn['plural'] !== '') {
            return $syn['plural'];
        }

        return $this->translator->trans($defaultKey, locale: $locale);
    }

    public function displayNameSingular(string $dataSource, string $defaultKey, ?string $locale = null): string
    {
        $locale ??= $this->translator->getLocale();
        $syn = $this->synonyms($dataSource, $locale);

        if ($syn['singular'] !== '') {
            return $syn['singular'];
        }

        return $this->translator->trans($defaultKey, locale: $locale);
    }

    private function synonyms(string $dataSource, string $locale): array
    {
        $all = $this->synonymsSettings->getSynonymsAsArray();
        $row = $all[$dataSource][$locale] ?? ['singular' => '', 'plural' => ''];

        return [
            'singular' => (string)($row['singular'] ?? ''),
            'plural'   => (string)($row['plural'] ?? ''),
        ];
    }
}
