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

use App\Services\SIFormatter;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SIFormatterTest extends WebTestCase
{
    /**
     * @var SIFormatter
     */
    protected $service;

    protected function setUp(): void
    {
        //Get an service instance.
        self::bootKernel();
        //$this->service = self::$container->get(SIFormatter::class);

        $this->service = new SIFormatter();
    }

    public function testGetMagnitude(): void
    {
        //Get an service instance.
        $this->assertSame(0, $this->service->getMagnitude(7.0));
        $this->assertSame(0, $this->service->getMagnitude(9.0));
        $this->assertSame(0, $this->service->getMagnitude(0.0));
        $this->assertSame(0, $this->service->getMagnitude(-1.0));
        $this->assertSame(0, $this->service->getMagnitude(-9.9));

        $this->assertSame(3, $this->service->getMagnitude(9999.99));
        $this->assertSame(3, $this->service->getMagnitude(1000.0));
        $this->assertSame(3, $this->service->getMagnitude(-9999.99));
        $this->assertSame(3, $this->service->getMagnitude(-1000.0));

        $this->assertSame(-1, $this->service->getMagnitude(0.1));
        $this->assertSame(-1, $this->service->getMagnitude(-0.9999));

        $this->assertSame(-25, $this->service->getMagnitude(-1.246e-25));
        $this->assertSame(12, $this->service->getMagnitude(9.99e12));
    }

    public function testgetPrefixByMagnitude(): void
    {
        $this->assertSame([1, ''], $this->service->getPrefixByMagnitude(2));

        $this->assertSame([1000, 'k'], $this->service->getPrefixByMagnitude(3));
        $this->assertSame([1000, 'k'], $this->service->getPrefixByMagnitude(4));
        $this->assertSame([1000, 'k'], $this->service->getPrefixByMagnitude(5));

        $this->assertSame([0.001, 'm'], $this->service->getPrefixByMagnitude(-3));
        $this->assertSame([0.001, 'm'], $this->service->getPrefixByMagnitude(-2));
        $this->assertSame([0.001, 'm'], $this->service->getPrefixByMagnitude(-4));
    }

    public function testFormat(): void
    {
        $this->assertSame('2.32 km', $this->service->format(2321, 'm'));
        $this->assertSame('230.45 km', $this->service->format(230450.3, 'm'));
        $this->assertSame('-98.20 mg', $this->service->format(-0.0982, 'g'));
        $this->assertSame('-0.23 g', $this->service->format(-0.23, 'g'));
    }
}
