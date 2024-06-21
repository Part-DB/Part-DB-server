<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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
namespace App\Tests\Services\LabelSystem\Barcodes;

use App\Entity\LabelSystem\BarcodeType;
use App\Services\LabelSystem\Barcodes\BarcodeHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BarcodeHelperTest extends WebTestCase
{

    protected ?BarcodeHelper $service = null;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(BarcodeHelper::class);
    }

    public function testBarcodeAsHTML(): void
    {
        $html = $this->service->barcodeAsHTML('Test', BarcodeType::QR);
        $this->assertStringStartsWith('<img', $html);
        $this->assertStringContainsString('alt="Test"', $html);
    }

    public function testBarcodeAsSVG(): void
    {
        //Test that all barcodes types are supported
        foreach (BarcodeType::cases() as $type) {
            //Skip NONE type
            if (BarcodeType::NONE === $type) {
                continue;
            }

            $svg = $this->service->barcodeAsSVG('1234', $type);

            $this->assertStringContainsStringIgnoringCase('SVG', $svg);
        }
    }

    public function testBarcodeAsSVGNoneType(): void
    {
        //On NONE type, service must throw an exception.
        $this->expectException(\InvalidArgumentException::class);

        $this->service->barcodeAsSVG('test', BarcodeType::NONE);
    }
}
