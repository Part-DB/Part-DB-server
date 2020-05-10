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

use App\Services\LabelSystem\Barcodes\BarcodeNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BarcodeNormalizerTest extends WebTestCase
{

    /** @var BarcodeNormalizer */
    protected $service;

    public function setUp(): void
    {
        self::bootKernel();
        $this->service = self::$container->get(BarcodeNormalizer::class);
    }

    public function dataProvider(): array
    {
        return [
            //QR URL content:
            [['lot', 1], 'https://localhost:8000/scan/lot/1'],
            [['part', 123], 'https://localhost:8000/scan/part/123'],
            [['location', 4], 'http://foo.bar/part-db/scan/location/4'],
            [['under_score', 10], 'http://test/part-db/sub/scan/under_score/10/'],
            //Current Code39 format:
            [['lot', 10], 'L0010'],
            [['lot', 123], 'L0123'],
            [['lot', 123456], 'L123456'],
            [['part', 2], 'P0002'],
            //Development phase Code39 barcodes:
            [['lot', 10], 'L-000010'],
            [['lot', 10], 'Lß000010'],
            [['part', 123], 'P-000123'],
            [['location', 123], 'S-000123'],
            [['lot', 12345678], 'L-12345678'],
            //Legacy storelocation format
            [['location', 336], '$L00336'],
            [['location', 12345678], '$L12345678'],
            //Legacy Part format
            [['part', 123], '0000123'],
            [['part', 123], '00001236'],
            [['part', 1234567], '12345678'],
        ];
    }

    public function invalidDataProvider(): array
    {
        return [
            ['https://localhost/part/1'], //Without scan
            ['L-'], //Without number
            ['L-123'], //Too short
            ['X-123456'], //Unknown prefix
            ['XXXWADSDF sdf'], //Garbage
            [''], //Empty
        ];
    }

    /**
     * @dataProvider dataProvider
     */
    public function testNormalizeBarcodeContent(array $expected, string $input)
    {
        $this->assertSame($expected, $this->service->normalizeBarcodeContent($input));
    }

    /**
     * @dataProvider invalidDataProvider
     */
    public function testInvalidFormats(string $input)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->normalizeBarcodeContent($input);
    }
}
