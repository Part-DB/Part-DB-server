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

declare(strict_types=1);

namespace App\Tests\Entity\Parts;

use App\Entity\Parts\PartLot;
use DateTime;
use PHPUnit\Framework\TestCase;

class PartLotTest extends TestCase
{
    public function testIsExpired(): void
    {
        $lot = new PartLot();
        $this->assertNull($lot->isExpired(), 'Lot must be return null when no Expiration date is set!');

        $datetime = new DateTime();

        $lot->setExpirationDate($datetime->setTimestamp(strtotime('now +1 hour')));
        $this->assertFalse($lot->isExpired(), 'Lot with expiration date in the future must not be expired!');

        $lot->setExpirationDate($datetime->setTimestamp(strtotime('now -1 hour')));
        $this->assertTrue($lot->isExpired(), 'Lot with expiration date in the past must be expired!');
    }
}
