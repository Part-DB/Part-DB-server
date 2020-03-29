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

namespace App\Tests\Services;

use App\Entity\Parts\Part;
use App\Entity\PriceInformations\Orderdetail;
use App\Entity\PriceInformations\Pricedetail;
use App\Services\AmountFormatter;
use App\Services\PricedetailHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PricedetailHelperTest extends WebTestCase
{
    /**
     * @var AmountFormatter
     */
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        //Get an service instance.
        self::bootKernel();
        $this->service = self::$container->get(PricedetailHelper::class);
    }

    public function maxDiscountAmountDataProvider(): ?\Generator
    {
        $part = new Part();
        yield [$part, null, 'Part without any orderdetails failed!'];

        //Part with empty orderdetails
        $part = new Part();
        $orderdetail = new Orderdetail();
        $part->addOrderdetail($orderdetail);
        yield [$part, null, 'Part with one empty orderdetail failed!'];

        $part = new Part();
        $orderdetail = new Orderdetail();
        $part->addOrderdetail($orderdetail);
        $orderdetail->addPricedetail((new Pricedetail())->setMinDiscountQuantity(1));
        yield [$part, 1.0, 'Part with one pricedetail failed!'];

        $part = new Part();
        $orderdetail = new Orderdetail();
        $part->addOrderdetail($orderdetail);
        $orderdetail->addPricedetail((new Pricedetail())->setMinDiscountQuantity(1));
        $orderdetail->addPricedetail((new Pricedetail())->setMinDiscountQuantity(2));
        $orderdetail->addPricedetail((new Pricedetail())->setMinDiscountQuantity(1.5));
        yield [$part, 2.0, 'Part with multiple pricedetails failed!'];

        $part = new Part();
        $orderdetail = new Orderdetail();
        $orderdetail2 = new Orderdetail();
        $part->addOrderdetail($orderdetail);
        $part->addOrderdetail($orderdetail2);
        $orderdetail->addPricedetail((new Pricedetail())->setMinDiscountQuantity(1));
        $orderdetail->addPricedetail((new Pricedetail())->setMinDiscountQuantity(2));
        $orderdetail->addPricedetail((new Pricedetail())->setMinDiscountQuantity(1.5));
        $orderdetail2->addPricedetail((new Pricedetail())->setMinDiscountQuantity(10));

        yield [$part, 10.0, 'Part with multiple orderdetails failed'];
    }

    /**
     * @dataProvider maxDiscountAmountDataProvider
     */
    public function testGetMaxDiscountAmount(Part $part, ?float $expected_result, string $message): void
    {
        $this->assertSame($expected_result, $this->service->getMaxDiscountAmount($part), $message);
    }
}
