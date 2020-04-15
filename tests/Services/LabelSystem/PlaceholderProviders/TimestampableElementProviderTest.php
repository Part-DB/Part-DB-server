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

use App\Entity\Contracts\TimeStampableInterface;
use App\Services\LabelSystem\PlaceholderProviders\GlobalProviders;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TimestampableElementProviderTest extends WebTestCase
{
    /** @var GlobalProviders */
    protected $service;

    protected $target;

    public function setUp(): void
    {
        self::bootKernel();
        $this->service = self::$container->get(GlobalProviders::class);
        $this->target = new class implements TimeStampableInterface {

            /**
             * @inheritDoc
             */
            public function getLastModified(): ?DateTime
            {
                return new \DateTime('2000-01-01');
            }

            /**
             * @inheritDoc
             */
            public function getAddedDate(): ?DateTime
            {
                return new \DateTime('2000-01-01');
            }
        };
    }

    public function dataProvider(): array
    {
        return [
            ['2000-01-01', '%%LAST_MODIFIED%%'],
            ['2000-01-01', '%%CREATION_DATE%%'],
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
