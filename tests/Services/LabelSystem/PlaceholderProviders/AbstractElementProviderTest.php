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

namespace App\Tests\Services\LabelSystem\PlaceholderProviders;

use App\Entity\Base\AbstractDBElement;
use App\Entity\Parts\Part;
use App\Services\LabelSystem\PlaceholderProviders\AbstractDBElementProvider;
use App\Services\LabelSystem\PlaceholderProviders\GlobalProviders;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AbstractElementProviderTest extends WebTestCase
{
    /** @var AbstractDBElementProvider */
    protected $service;

    protected $target;

    public function setUp(): void
    {
        self::bootKernel();
        $this->service = self::$container->get(AbstractDBElementProvider::class);
        $this->target = new class extends AbstractDBElement {
            protected $id = 123;

            /**
             * @inheritDoc
             */
            public function getIDString(): string
            {
                return 'ignore';
            }
        };
    }

    public function dataProvider(): array
    {
        return [
            ['123', '%%ID%%'],
        ];
    }

    /**
     * @dataProvider dataProvider
     */
    public function testReplace(string $expected, string $placeholder): void
    {
        $this->assertSame($expected, $this->service->replace($placeholder, $this->target));
    }
}
