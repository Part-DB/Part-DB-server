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


namespace App\Settings\SystemSettings;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

enum HomepageItems: string implements TranslatableInterface
{
    case SEARCH = 'search';
    case BANNER = 'banner';
    case LICENSE = 'license';
    case FIRST_STEPS = 'first_steps';
    case LAST_ACTIVITY = 'last_activity';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        $key = match($this) {
            self::SEARCH => 'search.placeholder',
            self::BANNER => 'settings.system.customization.banner',
            self::LICENSE => 'homepage.license',
            self::FIRST_STEPS => 'homepage.first_steps.title',
            self::LAST_ACTIVITY => 'homepage.last_activity',
        };

        return $translator->trans($key, locale: $locale);
    }
}
