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

namespace App\Tests\Services\LabelSystem;

use App\Entity\Base\AbstractDBElement;
use App\Entity\LabelSystem\LabelOptions;
use App\Entity\LabelSystem\LabelSupportedElement;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\StorageLocation;
use App\Services\LabelSystem\LabelGenerator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LabelGeneratorTest extends WebTestCase
{
    /**
     * @var LabelGenerator
     */
    protected $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(LabelGenerator::class);
    }

    public static function supportsDataProvider(): \Iterator
    {
        yield [LabelSupportedElement::PART, Part::class];
        yield [LabelSupportedElement::PART_LOT, PartLot::class];
        yield [LabelSupportedElement::STORELOCATION, StorageLocation::class];
    }

    /**
     * @dataProvider supportsDataProvider
     */
    public function testSupports(LabelSupportedElement $type, string $class): void
    {
        $options = new LabelOptions();
        $options->setSupportedElement($type);

        //Ensure that the given class is supported
        $this->assertTrue($this->service->supports($options, new $class()));

        //Ensure that another class is not supported
        $not_supported = new class() extends AbstractDBElement {
        };

        $this->assertFalse($this->service->supports($options, $not_supported));
    }

    public function testMmToPointsArray(): void
    {
        $this->assertSame(
            [0.0, 0.0, 141.7325, 85.0395],
            $this->service->mmToPointsArray(50.0, 30.0)
        );
    }

    public function testGenerateLabel(): void
    {
        $part = new Part();
        $options = new LabelOptions();
        $options->setLines('Test');
        $options->setSupportedElement(LabelSupportedElement::PART);

        //Test for a single passed element:
        $pdf = $this->service->generateLabel($options, $part);
        //Just a simple check if a PDF is returned
        $this->assertStringStartsWith('%PDF-', $pdf);

        //Test for an array of elements
        $pdf = $this->service->generateLabel($options, [$part, $part]);
        //Just a simple check if a PDF is returned
        $this->assertStringStartsWith('%PDF-', $pdf);
    }
}
