<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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
namespace App\Entity\LogSystem;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The access method (surface) that was used to make the change associated with a log entry.
 */
enum AccessMethod: int implements TranslatableInterface
{
    case WEB = 1;
    case CLI = 2;
    case API = 3;
    case MCP = 4;

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('log.access_method.' . strtolower($this->name), locale: $locale);
    }

    /**
     * Returns the FontAwesome icon class (without the "fa-solid"/"fa-fw" style classes) representing this access method.
     */
    public function getIconClass(): string
    {
        return match ($this) {
            self::WEB => 'fa-globe',
            self::CLI => 'fa-terminal',
            self::API => 'fa-plug',
            self::MCP => 'fa-robot',
        };
    }
}
