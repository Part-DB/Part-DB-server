<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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
namespace App\Tests\Controller\Helpers;

use App\Controller\Helpers\SafeRedirectTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class SafeRedirectTraitTest extends TestCase
{
    private function sanitize(mixed $url): ?string
    {
        $controller = new class extends AbstractController {
            use SafeRedirectTrait;

            public static function callSanitize(mixed $url): ?string
            {
                return self::sanitizeRedirectTarget($url);
            }
        };

        return $controller::callSanitize($url);
    }

    public static function localPathProvider(): \Iterator
    {
        yield ['/'];
        yield ['/en/part/1'];
        yield ['/partdb/en/parts?page=2&filter%5Bname%5D=foo'];
        yield ['/en/part/1#lots'];
    }

    public static function unsafeProvider(): \Iterator
    {
        yield [null];
        yield [''];
        yield [123];
        yield ['https://evil.example/'];
        yield ['http://evil.example/'];
        yield ['https:/evil.example/'];
        yield ['https:evil.example'];
        yield ['https:///evil.example'];
        yield ['HTTPS:/evil.example'];
        yield ['//evil.example/'];
        yield ['/\\evil.example/'];
        yield ['\\\\evil.example'];
        yield ["/\t/evil.example"];
        yield ["/\n/evil.example"];
        yield [' /en/part/1'];
        yield ['/en/part/1\\..'];
        yield ['javascript:alert(1)'];
        yield ['en/part/1'];
    }

    #[DataProvider('localPathProvider')]
    public function testLocalPathsAreAccepted(string $url): void
    {
        self::assertSame($url, $this->sanitize($url));
    }

    #[DataProvider('unsafeProvider')]
    public function testUnsafeTargetsAreRejected(mixed $url): void
    {
        self::assertNull($this->sanitize($url));
    }
}
