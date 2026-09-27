<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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
namespace App\Tests\Entity\PriceInformations;

use App\Entity\PriceInformations\Orderdetail;
use PHPUnit\Framework\TestCase;

final class OrderdetailTest extends TestCase
{
    public function testAvailableAmountIsUnknownByDefault(): void
    {
        $orderdetail = new Orderdetail();

        $this->assertNull($orderdetail->getAvailableAmount());
        $this->assertNull($orderdetail->getAvailableAmountUpdatedAt());
    }

    public function testSettingTheAvailableAmountStampsTheCurrentTime(): void
    {
        $before = new \DateTimeImmutable();
        $orderdetail = (new Orderdetail())->setAvailableAmount(100.0);

        $this->assertSame(100.0, $orderdetail->getAvailableAmount());
        $this->assertNotNull($orderdetail->getAvailableAmountUpdatedAt());
        $this->assertGreaterThanOrEqual($before, $orderdetail->getAvailableAmountUpdatedAt());
    }

    public function testTheTimeOfTheAvailableAmountCanBeGiven(): void
    {
        //The value can come from an info provider cache, so the caller can tell when it was actually retrieved
        $retrieved_at = new \DateTimeImmutable('2026-09-01 12:00:00');
        $orderdetail = (new Orderdetail())->setAvailableAmount(100.0, $retrieved_at);

        $this->assertEquals($retrieved_at, $orderdetail->getAvailableAmountUpdatedAt());
    }

    public function testAStockOfZeroIsKeptAsKnownStock(): void
    {
        //A stock of 0 is a known stock ("out of stock"), which is something different from an unknown stock
        $orderdetail = (new Orderdetail())->setAvailableAmount(0.0);

        $this->assertSame(0.0, $orderdetail->getAvailableAmount());
        $this->assertNotNull($orderdetail->getAvailableAmountUpdatedAt());
    }

    public function testResettingTheAvailableAmountClearsTheTime(): void
    {
        $orderdetail = (new Orderdetail())->setAvailableAmount(100.0);

        $orderdetail->setAvailableAmount(null);

        $this->assertNull($orderdetail->getAvailableAmount());
        $this->assertNull($orderdetail->getAvailableAmountUpdatedAt());
    }
}
