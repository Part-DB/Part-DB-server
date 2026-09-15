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

namespace App\Entity\Parts;

/**
 * The semantic Bootstrap color a PartCustomState can be rendered with.
 * This is a closed whitelist: no free-form CSS classes or colors can be stored.
 */
enum PartCustomStateColor: string
{
    case PRIMARY = 'primary';
    case SECONDARY = 'secondary';
    case INFO = 'info';
    case SUCCESS = 'success';
    case WARNING = 'warning';
    case DANGER = 'danger';
    case LIGHT = 'light';
    case DARK = 'dark';

    public function toTranslationKey(): string
    {
        return 'part_custom_state.color.' . $this->value;
    }

    /**
     * Maps this color to the fixed Bootstrap badge class it is rendered with.
     * This is the only place that translates a stored color into a CSS class.
     */
    public function toBadgeClass(): string
    {
        return 'text-bg-' . $this->value;
    }
}
