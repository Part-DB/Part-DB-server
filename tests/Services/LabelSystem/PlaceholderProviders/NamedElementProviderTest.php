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

namespace App\Tests\Services\LabelSystem\PlaceholderProviders;

use PHPUnit\Framework\Attributes\DataProvider;
use App\Entity\Contracts\NamedElementInterface;
use App\Services\LabelSystem\PlaceholderProviders\NamedElementProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class NamedElementProviderTest extends WebTestCase
{
    /**
     * @var NamedElementProvider
     */
    protected $service;

    protected $target;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(NamedElementProvider::class);
        $this->target = new class() implements NamedElementInterface {
            public function getName(): string
            {
                return 'This is my Name';
            }
        };
    }

    public static function dataProvider(): \Iterator
    {
        yield ['This is my Name', '[[NAME]]'];
    }

    #[DataProvider('dataProvider')]
    public function testReplace(string $expected, string $placeholder): void
    {
        $this->assertSame($expected, $this->service->replace($placeholder, $this->target));
    }
}
