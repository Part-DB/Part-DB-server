<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2025 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Settings\InfoProviderSystem;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * This environment variable determines the sorting criteria for product results.
 * The sorting process first arranges items based on the provided keyword.
 * Then, if set to 'C', it further sorts by completeness (prioritizing items with the most
 * detailed information). If set to 'M', it further sorts by manufacturer name.
 * If unset or set to any other value, no sorting is performed.
 */
enum OEMSecretsSortMode : string implements TranslatableInterface
{
    case NONE = "N";
    case COMPLETENESS = "C";
    case MANUFACTURER = "M";

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('settings.ips.oemsecrets.sortMode.' . $this->value, locale: $locale);
    }
}