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

namespace App\Tests\DataTables\Filters;

use App\DataTables\Filters\PartSearchSort;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class PartSearchSortTest extends TestCase
{
    public function testFromRequest(): void
    {
        $this->assertSame(PartSearchSort::MANUFACTURER, PartSearchSort::fromRequest(new Request(['sort' => 'manufacturer'])));
        $this->assertNull(PartSearchSort::fromRequest(new Request(['sort' => 'invalid'])));
        $this->assertNull(PartSearchSort::fromRequest(new Request()));
    }

    public function testDirectionFromRequest(): void
    {
        $this->assertSame(\SortDirection::Descending, PartSearchSort::directionFromRequest(new Request(['sort_dir' => 'desc'])));
        $this->assertSame(\SortDirection::Descending, PartSearchSort::directionFromRequest(new Request(['sort_dir' => 'DESC'])));
        $this->assertSame(\SortDirection::Ascending, PartSearchSort::directionFromRequest(new Request(['sort_dir' => 'asc'])));
        $this->assertSame(\SortDirection::Ascending, PartSearchSort::directionFromRequest(new Request()));
    }
}
