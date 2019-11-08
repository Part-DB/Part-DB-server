<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Tests\Entity\PriceSystem;

use App\Entity\PriceInformations\Orderdetail;
use App\Entity\PriceInformations\Pricedetail;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\TestCase;

class OrderdetailTest extends TestCase
{
    public function testAddRemovePricdetails()
    {
        $orderdetail = new Orderdetail();
        $this->assertInstanceOf(Collection::class, $orderdetail->getPricedetails());
        $this->assertTrue($orderdetail->getPricedetails()->isEmpty());

        $pricedetail = new Pricedetail();
        $orderdetail->addPricedetail($pricedetail);
        $this->assertEquals($orderdetail, $pricedetail->getOrderdetail());
        $this->assertEquals(1, $orderdetail->getPricedetails()->count());

        //After removal of the pricedetail, the orderdetail must be empty again
        $orderdetail->removePricedetail($pricedetail);
        $this->assertTrue($orderdetail->getPricedetails()->isEmpty());
    }

    public function testFindPriceForQty()
    {
        $price0 = (new Pricedetail())->setMinDiscountQuantity(0.23);
        $price1 = (new Pricedetail())->setMinDiscountQuantity(1);
        $price5 = (new Pricedetail())->setMinDiscountQuantity(5.3);
        $orderdetail = (new Orderdetail())->addPricedetail($price0)->addPricedetail($price1)->addPricedetail($price5);

        $this->assertNull($orderdetail->findPriceForQty(0));
        $this->assertNull($orderdetail->findPriceForQty(0.1));

        $this->assertEquals($price0, $orderdetail->findPriceForQty(0.5));
        $this->assertEquals($price1, $orderdetail->findPriceForQty(1));
        $this->assertEquals($price1, $orderdetail->findPriceForQty(1.5));
        $this->assertEquals($price5, $orderdetail->findPriceForQty(5.3));
        $this->assertEquals($price5, $orderdetail->findPriceForQty(10000));
    }
}
