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

namespace App\Tests\Services\LabelSystem\Barcodes;

use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\Storelocation;
use App\Services\LabelSystem\Barcodes\BarcodeContentGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class BarcodeContentGeneratorTest extends KernelTestCase
{
    /** @var BarcodeContentGenerator */
    private $service;

    public function setUp(): void
    {
        self::bootKernel();
        $this->service = self::$container->get(BarcodeContentGenerator::class);
    }


    public function Barcode1DDataProvider(): array
    {
        return [
            ['P0000', Part::class],
            ['L0000', PartLot::class],
            ['S0000', Storelocation::class],
        ];
    }

    public function Barcode2DDataProvider(): array
    {
        return [
            ['/scan/part/0', Part::class],
            ['/scan/lot/0', PartLot::class],
            ['/scan/location/0', Storelocation::class]
        ];
    }

    /**
     * @dataProvider Barcode1DDataProvider
     */
    public function testGet1DBarcodeContent(string $expected, string $class): void
    {
        $this->assertSame($expected, $this->service->get1DBarcodeContent(new $class()));
    }

    /**
     * @dataProvider Barcode2DDataProvider
     */
    public function testGetURLContent(string $expected, string $class): void
    {
        $url = $this->service->getURLContent(new $class());
        //URL must be absolute...
        $this->assertStringStartsWith('http', $url);

        $this->assertStringEndsWith($expected, $url);
    }
}
