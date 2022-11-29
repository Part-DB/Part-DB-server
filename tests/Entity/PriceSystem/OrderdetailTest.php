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

namespace App\Tests\Entity\PriceSystem;

use App\Entity\PriceInformations\Orderdetail;
use App\Entity\PriceInformations\Pricedetail;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\TestCase;

class OrderdetailTest extends TestCase
{
    public function testAddRemovePricdetails(): void
    {
        $orderdetail = new Orderdetail();
        $this->assertInstanceOf(Collection::class, $orderdetail->getPricedetails());
        $this->assertTrue($orderdetail->getPricedetails()->isEmpty());

        $pricedetail = new Pricedetail();
        $orderdetail->addPricedetail($pricedetail);
        $this->assertSame($orderdetail, $pricedetail->getOrderdetail());
        $this->assertSame(1, $orderdetail->getPricedetails()->count());

        //After removal of the pricedetail, the orderdetail must be empty again
        $orderdetail->removePricedetail($pricedetail);
        $this->assertTrue($orderdetail->getPricedetails()->isEmpty());
    }

    public function testFindPriceForQty(): void
    {
        $price0 = (new Pricedetail())->setMinDiscountQuantity(0.23);
        $price1 = (new Pricedetail())->setMinDiscountQuantity(1);
        $price5 = (new Pricedetail())->setMinDiscountQuantity(5.3);
        $orderdetail = (new Orderdetail())->addPricedetail($price0)->addPricedetail($price1)->addPricedetail($price5);

        $this->assertNull($orderdetail->findPriceForQty(0));
        $this->assertNull($orderdetail->findPriceForQty(0.1));

        $this->assertSame($price0, $orderdetail->findPriceForQty(0.5));
        $this->assertSame($price1, $orderdetail->findPriceForQty(1));
        $this->assertSame($price1, $orderdetail->findPriceForQty(1.5));
        $this->assertSame($price5, $orderdetail->findPriceForQty(5.3));
        $this->assertSame($price5, $orderdetail->findPriceForQty(10000));
    }
}
