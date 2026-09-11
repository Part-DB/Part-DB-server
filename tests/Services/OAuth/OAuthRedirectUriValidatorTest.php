<?php
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

declare(strict_types=1);

namespace App\Tests\Services\OAuth;

use App\Services\OAuth\OAuthRedirectUriValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OAuthRedirectUriValidatorTest extends TestCase
{
    private OAuthRedirectUriValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new OAuthRedirectUriValidator();
    }

    public static function isAllowedDataProvider(): \Iterator
    {
        // https:// with a host is always allowed
        yield ['https://example.com/callback', true];
        yield ['https://example.com', true];
        yield ['HTTPS://Example.com/callback', true];
        // https:// without a host is rejected
        yield ['https:///callback', false];

        // http:// loopback is allowed, on any port
        yield ['http://127.0.0.1/cb', true];
        yield ['http://127.0.0.1:8080/cb', true];
        yield ['http://localhost/cb', true];
        yield ['http://localhost:12345/cb', true];
        yield ['http://[::1]/cb', true];
        yield ['http://[::1]:9000/cb', true];
        yield ['HTTP://LOCALHOST/cb', true];
        // http:// to a non-loopback host is rejected (insecure channel)
        yield ['http://example.com/cb', false];
        yield ['http://evil.com/cb', false];

        // private-use scheme with a dot (reverse-DNS style) is allowed
        yield ['com.example.app:/callback', true];
        yield ['com.example.app://callback', true];
        yield ['COM.EXAMPLE.APP:/callback', true];
        // private-use scheme without a dot is rejected
        yield ['example:/callback', false];
        yield ['myapp:/callback', false];

        // any URI with a fragment is rejected, regardless of scheme
        yield ['https://example.com/cb#frag', false];
        yield ['com.example.app:/callback#frag', false];

        // malformed / schemeless input is rejected
        yield ['not a uri', false];
        yield ['http:///example.com', false];
        yield ['/relative/path', false];
        yield ['', false];
    }

    #[DataProvider('isAllowedDataProvider')]
    public function testIsAllowed(string $uri, bool $expected): void
    {
        $this->assertSame($expected, $this->validator->isAllowed($uri));
    }
}
