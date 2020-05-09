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

namespace App\Tests\Services\LabelSystem;

use App\Entity\LabelSystem\LabelOptions;
use App\Entity\Parts\Part;
use App\Services\LabelSystem\BarcodeGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class BarcodeGeneratorTest extends WebTestCase
{

    /** @var BarcodeGenerator */
    protected $services;

    public function setUp(): void
    {
        self::bootKernel();
        $this->services = self::$container->get(BarcodeGenerator::class);
    }

    public function testGetContent(): void
    {
        $part = new Part();
        $part->setName('Test');

        //Test that all barcodes types are supported
        foreach (LabelOptions::BARCODE_TYPES as $type) {
            $options = new LabelOptions();
            $options->setBarcodeType($type);
            $content = $this->services->generateSVG($options, $part);

            //When type is none, service must return null.
            if ($type === 'none') {
                $this->assertNull($content);
            } else {
                $this->assertIsString($content);
            }
        }
    }

    public function testGenerateSVG(): void
    {
        $part = new Part();
        $part->setName('Test');

        //Test that all barcodes types are supported
        foreach (LabelOptions::BARCODE_TYPES as $type) {
            $options = new LabelOptions();
            $options->setBarcodeType($type);
            $svg = $this->services->generateSVG($options, $part);

            //When type is none, service must return null.
            if ($type === "none") {
                $this->assertNull($svg);
            } else {
                $this->assertStringContainsStringIgnoringCase("SVG", $svg);
            }
        }
    }
}
