<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

use App\Entity\LabelSystem\LabelSupportedElement;
use App\Services\LabelSystem\Barcodes\BarcodeRedirector;
use App\Services\LabelSystem\Barcodes\LocalBarcodeScanResult;
use App\Services\LabelSystem\Barcodes\BarcodeSourceType;
use Doctrine\ORM\EntityNotFoundException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class BarcodeRedirectorTest extends KernelTestCase
{
    private ?BarcodeRedirector $service = null;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(BarcodeRedirector::class);
    }

    public static function urlDataProvider(): \Iterator
    {
        yield [new LocalBarcodeScanResult(LabelSupportedElement::PART, 1, BarcodeSourceType::INTERNAL), '/en/part/1'];
        //Part lot redirects to Part info page (Part lot 1 is associated with part 3)
        yield [new LocalBarcodeScanResult(LabelSupportedElement::PART_LOT, 1, BarcodeSourceType::INTERNAL), '/en/part/3'];
        yield [new LocalBarcodeScanResult(LabelSupportedElement::STORELOCATION, 1, BarcodeSourceType::INTERNAL), '/en/store_location/1/parts'];
    }

    /**
     * @dataProvider urlDataProvider
     * @group DB
     */
    public function testGetRedirectURL(LocalBarcodeScanResult $scanResult, string $url): void
    {
        $this->assertSame($url, $this->service->getRedirectURL($scanResult));
    }

    public function testGetRedirectEntityNotFount(): void
    {
        $this->expectException(EntityNotFoundException::class);
        //If we encounter an invalid lot, we must throw an exception
        $this->service->getRedirectURL(new LocalBarcodeScanResult(LabelSupportedElement::PART_LOT,
            12_345_678, BarcodeSourceType::INTERNAL));
    }
}
