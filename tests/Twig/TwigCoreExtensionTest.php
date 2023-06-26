<?php

declare(strict_types=1);

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
namespace App\Tests\Twig;

use App\Twig\TwigCoreExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TwigCoreExtensionTest extends WebTestCase
{
    /** @var TwigCoreExtension */
    protected $service;

    protected function setUp(): void
    {
        // TODO: Change the autogenerated stub

        //Get an service instance.
        self::bootKernel();
        $this->service = self::getContainer()->get(TwigCoreExtension::class);
    }

    public function testToArray(): void
    {
        //Check for simple arrays
        $this->assertSame([], $this->service->toArray([]));
        $this->assertSame([1, 2, 3], $this->service->toArray([1, 2, 3]));

        //Check for simple objects
        $this->assertSame([], $this->service->toArray(new \stdClass()));
        $this->assertSame(['test' => 1], $this->service->toArray((object)['test' => 1]));

        //Only test and test4 should be available
        $obj = new class {
            public $test = 1;
            protected $test2 = 3;
            private int $test3 = 5;
            private int $test4 = 7;

            public function getTest4(): int
            {
                return $this->test4;
            }
        };

        $this->assertEqualsCanonicalizing(['test' => 1, 'test4' => 7], $this->service->toArray($obj));
    }
}
