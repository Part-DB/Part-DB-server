<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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
namespace App\Tests\Entity\UserSystem;

use App\Entity\UserSystem\ApiTokenType;
use PHPUnit\Framework\TestCase;

class ApiTokenTypeTest extends TestCase
{

    public function testGetTokenPrefix(): void
    {
        $this->assertSame('tcp_', ApiTokenType::PERSONAL_ACCESS_TOKEN->getTokenPrefix());
    }

    public function testGetTypeFromToken(): void
    {
        $this->assertSame(ApiTokenType::PERSONAL_ACCESS_TOKEN, ApiTokenType::getTypeFromToken('tcp_123'));
    }

    public function testGetTypeFromTokenInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ApiTokenType::getTypeFromToken('tcp123');
    }

    public function testGetTypeFromTokenNonExisting(): void
    {
        $this->expectException(\ValueError::class);
        ApiTokenType::getTypeFromToken('abc_123');
    }
}
