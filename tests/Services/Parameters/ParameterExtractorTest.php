<?php

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

namespace App\Tests\Services\Parameters;

use App\Entity\Parameters\AbstractParameter;
use App\Services\Parameters\ParameterExtractor;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ParameterExtractorTest extends WebTestCase
{
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        //Get an service instance.
        self::bootKernel();
        $this->service = self::$container->get(ParameterExtractor::class);
    }

    public function emptyDataProvider(): array
    {
        return [
            [''],
            ['      '],
            ["\t\n"],
            [':;'],
            ['NPN Transistor'],
            ['=BC547 rewr'],
            ['<i>For good</i>, [b]bad[/b], evil'],
            ['Param:; Test'],
        ];
    }

    /**
     * @dataProvider emptyDataProvider
     */
    public function testShouldReturnEmpty(string $input): void
    {
        $this->assertEmpty($this->service->extractParameters($input));
    }

    public function testExtract(): void
    {
        $parameters = $this->service->extractParameters(' Operating Voltage:  10 V; Property : Value, Ström=1A (Test)');
        $this->assertContainsOnly(AbstractParameter::class, $parameters);
        $this->assertCount(3, $parameters);
        $this->assertSame('Operating Voltage', $parameters[0]->getName());
        $this->assertSame('10 V', $parameters[0]->getValueText());
        $this->assertSame('Property', $parameters[1]->getName());
        $this->assertSame('Value', $parameters[1]->getValueText());
        $this->assertSame('Ström', $parameters[2]->getName());
        $this->assertSame('1A (Test)', $parameters[2]->getValueText());
    }
}
