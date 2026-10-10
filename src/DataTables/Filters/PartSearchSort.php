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
namespace App\DataTables\Filters;

use Symfony\Component\HttpFoundation\Request;

/**
 * The orderings which can be chosen in the quick search for its results.
 * The value is used as "sort" query parameter by the search form and the autocomplete endpoint.
 */
enum PartSearchSort: string
{
    case NAME = 'name';
    case MANUFACTURER = 'manufacturer';
    case SUPPLIER = 'supplier';
    case ADDED_DATE = 'added';
    case LAST_MODIFIED = 'modified';
    /** The root category, the category of the part is (indirectly) contained in */
    case TOP_CATEGORY = 'top_category';
    /** The category directly assigned to the part */
    case CATEGORY = 'category';

    /**
     * Returns the ordering chosen in the given request (the "sort" query parameter), or null if the default
     * ordering should be used.
     */
    public static function fromRequest(Request $request): ?self
    {
        return self::tryFrom($request->query->getString('sort'));
    }

    /**
     * Returns true, if the given request asks for a descending order (the "sort_dir" query parameter).
     */
    public static function isDescending(Request $request): bool
    {
        return strtolower($request->query->getString('sort_dir')) === 'desc';
    }

    /**
     * Returns the name of the column of the parts table, which shows the value of this ordering, or null if the
     * parts table has no such column.
     */
    public function getTableColumn(): ?string
    {
        return match ($this) {
            self::NAME => 'name',
            self::MANUFACTURER => 'manufacturer',
            self::ADDED_DATE => 'addedDate',
            self::LAST_MODIFIED => 'lastModified',
            self::CATEGORY => 'category',
            self::SUPPLIER, self::TOP_CATEGORY => null,
        };
    }
}
