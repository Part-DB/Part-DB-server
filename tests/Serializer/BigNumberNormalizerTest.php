<?php
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

namespace App\Tests\Serializer;

use App\Serializer\BigNumberNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;

class BigNumberNormalizerTest extends WebTestCase
{
    /** @var BigNumberNormalizer */
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        //Get an service instance.
        self::bootKernel();
        $this->service = self::getContainer()->get(BigNumberNormalizer::class);
    }

    public function testNormalize(): void
    {
        $bigDecimal = BigDecimal::of('1.23456789');
        $this->assertSame('1.23456789', $this->service->normalize($bigDecimal));
    }

    public function testSupportsNormalization(): void
    {
        //Normalizer must only support BigNumber objects (and child classes)
        $this->assertFalse($this->service->supportsNormalization(new \stdClass()));

        $bigNumber = BigNumber::of(1);
        $this->assertTrue($this->service->supportsNormalization($bigNumber));

        $bigDecimal = BigDecimal::of(1);
        $this->assertTrue($this->service->supportsNormalization($bigDecimal));
    }
}
