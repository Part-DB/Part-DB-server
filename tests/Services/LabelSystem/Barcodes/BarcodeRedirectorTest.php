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

namespace App\Tests\Services\LabelSystem\Barcodes;

use App\Services\LabelSystem\Barcodes\BarcodeRedirector;
use Doctrine\ORM\EntityNotFoundException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class BarcodeRedirectorTest extends KernelTestCase
{
    private ?object $service = null;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(BarcodeRedirector::class);
    }

    public function urlDataProvider(): array
    {
        return [
            ['part', '/en/part/1'],
            //Part lot redirects to Part info page (Part lot 1 is associated with part 3
            ['lot', '/en/part/3'],
            ['location', '/en/store_location/1/parts'],
        ];
    }

    /**
     * @dataProvider urlDataProvider
     * @group DB
     */
    public function testGetRedirectURL(string $type, string $url): void
    {
        $this->assertSame($url, $this->service->getRedirectURL($type, 1));
    }

    public function testGetRedirectEntityNotFount(): void
    {
        $this->expectException(EntityNotFoundException::class);
        //If we encounter an invalid lot, we must throw an exception
        $this->service->getRedirectURL('lot', 12_345_678);
    }

    public function testInvalidType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->getRedirectURL('invalid', 1);
    }
}
