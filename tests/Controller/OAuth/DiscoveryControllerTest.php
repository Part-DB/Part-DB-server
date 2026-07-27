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

namespace App\Tests\Controller\OAuth;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Drives the real unauthenticated GET /.well-known/oauth-* HTTP flow
 * (App\Controller\OAuth\DiscoveryController).
 */
final class DiscoveryControllerTest extends WebTestCase
{
    public function testAuthorizationServerMetadata(): void
    {
        $httpClient = static::createClient();
        $httpClient->request('GET', '/.well-known/oauth-authorization-server');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        $data = json_decode((string) $httpClient->getResponse()->getContent(), true);

        self::assertStringEndsWith('/authorize', $data['authorization_endpoint']);
        self::assertStringEndsWith('/token', $data['token_endpoint']);
        self::assertStringEndsWith('/oauth/register', $data['registration_endpoint']);
        self::assertSame($data['issuer'], parse_url($data['authorization_endpoint'], PHP_URL_SCHEME).'://'.parse_url($data['authorization_endpoint'], PHP_URL_HOST));
        self::assertSame(['read_only', 'edit', 'admin', 'full'], $data['scopes_supported']);
        self::assertSame(['code'], $data['response_types_supported']);
        self::assertSame(['authorization_code', 'refresh_token'], $data['grant_types_supported']);
        self::assertSame(['none'], $data['token_endpoint_auth_methods_supported']);
        self::assertSame(['S256'], $data['code_challenge_methods_supported']);
    }

    public function testProtectedResourceMetadata(): void
    {
        $httpClient = static::createClient();
        $httpClient->request('GET', '/.well-known/oauth-protected-resource');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $httpClient->getResponse()->getContent(), true);

        self::assertSame([$data['resource']], $data['authorization_servers']);
        self::assertSame(['header'], $data['bearer_methods_supported']);
        self::assertSame(['read_only', 'edit', 'admin', 'full'], $data['scopes_supported']);
    }

    public function testDiscoveryEndpointsRequireNoAuthentication(): void
    {
        $httpClient = static::createClient();
        $httpClient->request('GET', '/.well-known/oauth-authorization-server');
        self::assertResponseStatusCodeSame(200);

        $httpClient->request('GET', '/.well-known/oauth-protected-resource');
        self::assertResponseStatusCodeSame(200);
    }
}
