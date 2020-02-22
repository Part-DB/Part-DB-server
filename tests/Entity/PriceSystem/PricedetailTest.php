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

use App\Entity\Parts\Part;
use App\Entity\PriceInformations\Orderdetail;
use App\Entity\PriceInformations\Pricedetail;
use PHPUnit\Framework\TestCase;

class PricedetailTest extends TestCase
{
    public function testGetPricePerUnit(): void
    {
        $pricedetail = new Pricedetail();
        $pricedetail->setPrice('100.234');

        $this->assertSame('100.23400', $pricedetail->getPricePerUnit());

        $pricedetail->setPriceRelatedQuantity(2.3);
        $this->assertSame('43.58000', $pricedetail->getPricePerUnit());
        $this->assertSame('139.45600', $pricedetail->getPricePerUnit('3.2'));

        $pricedetail->setPrice('10000000.2345'); //Ten million
        $pricedetail->setPriceRelatedQuantity(1.234e9); //100 billion
        $this->assertSame('0.00810', $pricedetail->getPricePerUnit());
    }

    public function testGetPriceRelatedQuantity(): void
    {
        $pricedetail = new Pricedetail();
        $part = $this->createMock(Part::class);
        $part->method('useFloatAmount')->willReturn(false);
        $orderdetail = $this->createMock(Orderdetail::class);
        $orderdetail->method('getPart')->willReturn($part);

        $part2 = $this->createMock(Part::class);
        $part2->method('useFloatAmount')->willReturn(true);
        $orderdetail2 = $this->createMock(Orderdetail::class);
        $orderdetail2->method('getPart')->willReturn($part2);

        //By default a price detail returns 1
        $this->assertSame(1.0, $pricedetail->getPriceRelatedQuantity());

        $pricedetail->setOrderdetail($orderdetail);
        $pricedetail->setPriceRelatedQuantity(10.23);
        $this->assertSame(10.0, $pricedetail->getPriceRelatedQuantity());
        //Price related quantity must not be zero!
        $pricedetail->setPriceRelatedQuantity(0.23);
        $this->assertSame(1.0, $pricedetail->getPriceRelatedQuantity());

        //With an part that has an float amount unit, also values like 0.23 can be returned
        $pricedetail->setOrderdetail($orderdetail2);
        $this->assertSame(0.23, $pricedetail->getPriceRelatedQuantity());
    }

    public function testGetMinDiscountQuantity(): void
    {
        $pricedetail = new Pricedetail();
        $part = $this->createMock(Part::class);
        $part->method('useFloatAmount')->willReturn(false);
        $orderdetail = $this->createMock(Orderdetail::class);
        $orderdetail->method('getPart')->willReturn($part);

        $part2 = $this->createMock(Part::class);
        $part2->method('useFloatAmount')->willReturn(true);
        $orderdetail2 = $this->createMock(Orderdetail::class);
        $orderdetail2->method('getPart')->willReturn($part2);

        //By default a price detail returns 1
        $this->assertSame(1.0, $pricedetail->getMinDiscountQuantity());

        $pricedetail->setOrderdetail($orderdetail);
        $pricedetail->setMinDiscountQuantity(10.23);
        $this->assertSame(10.0, $pricedetail->getMinDiscountQuantity());
        //Price related quantity must not be zero!
        $pricedetail->setMinDiscountQuantity(0.23);
        $this->assertSame(1.0, $pricedetail->getMinDiscountQuantity());

        //With an part that has an float amount unit, also values like 0.23 can be returned
        $pricedetail->setOrderdetail($orderdetail2);
        $this->assertSame(0.23, $pricedetail->getMinDiscountQuantity());
    }
}
