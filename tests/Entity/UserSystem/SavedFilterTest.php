<?php
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

declare(strict_types=1);

namespace App\Tests\Entity\UserSystem;

use App\Entity\UserSystem\SavedFilter;
use PHPUnit\Framework\TestCase;

final class SavedFilterTest extends TestCase
{
    public function testTargetKeepsOnlyRouteAndFilterParameters(): void
    {
        $filter = (new SavedFilter())->setTarget('part_list_tags', [
            '_locale' => 'de',
            'tag' => 'SMD',
            'invalid' => ['an' => 'array'],
        ], [
            'part_filter' => ['name' => ['operator' => '=', 'value' => 'R1']],
            '_dt' => 'dt',
            'keyword' => 'R1',
        ]);

        self::assertSame('part_list_tags', $filter->getRoute());
        self::assertSame(['tag' => 'SMD'], $filter->getRouteParams());
        self::assertSame([
            'part_filter' => ['name' => ['operator' => '=', 'value' => 'R1']],
            'keyword' => 'R1',
        ], $filter->getQuery());
    }

    public function testDateTimesAreStoredInUtc(): void
    {
        //The filter forms read date-times in the timezone of the current user, so a shared filter has to convert them
        $filter = (new SavedFilter())->setTarget('parts_show_all', [], ['part_filter' => [
            'lastModified' => ['operator' => 'BETWEEN', 'value1' => '2026-01-15T12:00', 'value2' => '2026-07-15T12:00:30'],
            'lotExpirationDate' => ['operator' => '<', 'value1' => '2026-01-15'],
            'name' => ['operator' => '=', 'value' => '2026-01-15T12:00'],
        ]], new \DateTimeZone('Europe/Berlin'));

        $utc = $filter->getQuery()['part_filter'];
        self::assertSame('2026-01-15T11:00', $utc['lastModified']['value1']);
        self::assertSame('2026-07-15T10:00:30', $utc['lastModified']['value2']);
        //Dates without a time and the values of other filters are kept as they are
        self::assertSame('2026-01-15', $utc['lotExpirationDate']['value1']);
        self::assertSame('2026-01-15T12:00', $utc['name']['value']);

        $newYork = $filter->getQuery(new \DateTimeZone('America/New_York'))['part_filter'];
        self::assertSame('2026-01-15T06:00', $newYork['lastModified']['value1']);
        self::assertSame('2026-07-15T06:00:30', $newYork['lastModified']['value2']);
        self::assertSame('2026-01-15', $newYork['lotExpirationDate']['value1']);
    }

    public function testOnlyPartListsAreAllowedAsTarget(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new SavedFilter())->setTarget('user_settings', [], []);
    }
}
