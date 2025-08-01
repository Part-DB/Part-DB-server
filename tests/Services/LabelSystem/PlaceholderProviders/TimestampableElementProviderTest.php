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

use App\Entity\Contracts\TimeStampableInterface;
use App\Services\LabelSystem\PlaceholderProviders\GlobalProviders;
use App\Services\LabelSystem\PlaceholderProviders\TimestampableElementProvider;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TimestampableElementProviderTest extends WebTestCase
{
    /**
     * @var GlobalProviders
     */
    protected $service;

    protected $target;

    protected function setUp(): void
    {
        self::bootKernel();
        \Locale::setDefault('en_US');
        $this->service = self::getContainer()->get(TimestampableElementProvider::class);
        $this->target = new class () implements TimeStampableInterface {
            public function getLastModified(): ?DateTime
            {
                return new DateTime('2000-01-01');
            }

            public function getAddedDate(): ?DateTime
            {
                return new DateTime('2000-01-01');
            }
        };
    }

    public function dataProvider(): \Iterator
    {
        \Locale::setDefault('en_US');
        // Use IntlDateFormatter like the actual service does
        $formatter = new \IntlDateFormatter(\Locale::getDefault(), \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
        $expectedFormat = $formatter->format(new DateTime('2000-01-01'));
        yield [$expectedFormat, '[[LAST_MODIFIED]]'];
        yield [$expectedFormat, '[[CREATION_DATE]]'];
    }

    /**
     * @dataProvider dataProvider
     */
    public function testReplace(string $expected, string $placeholder): void
    {
        $this->assertSame($expected, $this->service->replace($placeholder, $this->target));
    }
}
