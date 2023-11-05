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

use App\Entity\LabelSystem\BarcodeType;
use App\Entity\LabelSystem\LabelOptions;
use App\Entity\Parts\Part;
use App\Services\LabelSystem\LabelBarcodeGenerator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LabelBarcodeGeneratorTest extends WebTestCase
{
    protected ?LabelBarcodeGenerator $service = null;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(LabelBarcodeGenerator::class);
    }

    public function testGetContent(): void
    {
        $part = new Part();
        $part->setName('Test');

        //Test that all barcodes types are supported
        foreach (BarcodeType::cases() as $type) {
            $options = new LabelOptions();
            $options->setBarcodeType($type);
            $content = $this->service->generateSVG($options, $part);

            //When type is none, service must return null.
            if (BarcodeType::NONE === $type) {
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
        foreach (BarcodeType::cases() as $type) {
            $options = new LabelOptions();
            $options->setBarcodeType($type);
            $svg = $this->service->generateSVG($options, $part);

            //When type is none, service must return null.
            if (BarcodeType::NONE === $type) {
                $this->assertNull($svg);
            } else {
                $this->assertStringContainsStringIgnoringCase('SVG', $svg);
            }
        }
    }
}
