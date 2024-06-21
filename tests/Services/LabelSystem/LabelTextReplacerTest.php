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

use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Services\LabelSystem\LabelTextReplacer;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LabelTextReplacerTest extends WebTestCase
{
    /**
     * @var LabelTextReplacer
     */
    protected LabelTextReplacer $service;

    /**
     * @var Part
     */
    protected Part $target;

    protected function setUp(): void
    {
        //Get a service instance.
        self::bootKernel();
        $this->service = self::getContainer()->get(LabelTextReplacer::class);

        $this->target = new Part();
        $this->target->setName('Part 1');
        $this->target->setDescription('P Description');
        $this->target->setComment('P Comment');
    }

    public function handlePlaceholderDataProvider(): \Iterator
    {
        yield ['Part 1', '[[NAME]]'];
        yield ['P Description', '[[DESCRIPTION]]'];
        yield ['[[UNKNOWN]]', '[[UNKNOWN]]', '[[UNKNOWN]]'];
        yield ['[[INVALID', '[[INVALID'];
        yield ['[[', '[['];
        yield ['NAME', 'NAME'];
        yield ['[[NAME', '[[NAME'];
        yield ['Test [[NAME]]', 'Test [[NAME]]', 'Test [[NAME]]'];
    }

    public function replaceDataProvider(): \Iterator
    {
        yield ['Part 1', '[[NAME]]'];
        yield ['TestPart 1', 'Test[[NAME]]'];
        yield ["P Description\nPart 1", "[[DESCRIPTION_T]]\n[[NAME]]"];
        yield ['Part 1 Part 1', '[[NAME]] [[NAME]]'];
        yield ['[[UNKNOWN]] Test', '[[UNKNOWN]] Test'];
        yield ["[[NAME\n]] [[NAME ]]", "[[NAME\n]] [[NAME ]]"];
        yield ['[[]]', '[[]]'];
        yield ['TEST[[ ]]TEST', 'TEST[[ ]]TEST'];
    }

    /**
     * @dataProvider handlePlaceholderDataProvider
     */
    public function testHandlePlaceholder(string $expected, string $input): void
    {
        $this->assertSame($expected, $this->service->handlePlaceholder($input, $this->target));
    }

    /**
     * @dataProvider replaceDataProvider
     */
    public function testReplace(string $expected, string $input): void
    {
        $this->assertSame($expected, $this->service->replace($input, $this->target));
    }

    /**
     * Test if the part lot has the highest priority of all providers.
     */
    public function testPartLotPriority(): void
    {
        $part_lot = new PartLot();
        $part_lot->setDescription('Test');
        $part = new Part();
        $part->setName('Part');
        $part_lot->setPart($part);

        $this->assertSame('Part', $this->service->handlePlaceholder('[[NAME]]', $part_lot));
    }
}
