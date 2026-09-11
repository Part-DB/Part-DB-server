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


namespace App\Tests\Services\InfoProviderSystem\Providers;

use App\Services\InfoProviderSystem\Providers\TMEClient;
use App\Settings\InfoProviderSystem\TMESettings;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class TMEClientTest extends TestCase
{
    public function testMakeRequest(): void
    {
        $settings = $this->createMock(TMESettings::class);
        $settings->apiToken = 'test_token';
        $settings->apiSecret = 'test_secret';

        // Setup the Mock HTTP Client to return a fake auth token first, then actual responses
        $authResponse = new MockResponse(json_encode([
            'access_token' => 'fake_token_123',
            'token_type' => 'Bearer',
            'expires_in' => 300,
            'refresh_token' => 'fake_refresh'
        ]));

        $actionResponse1 = new MockResponse(json_encode(['status' => 'OK']));
        $actionResponse2 = new MockResponse(json_encode(['status' => 'OK']));

        $requests = [];
        $callback = function($method, $url, $options) use (&$requests, $authResponse, $actionResponse1, $actionResponse2) {
            $requests[] = ['method' => $method, 'url' => $url, 'options' => $options];
            if (count($requests) === 1) return $authResponse;
            if (count($requests) === 2) return $actionResponse1;
            return $actionResponse2;
        };

        $httpClient = new MockHttpClient($callback);
        $cache = new ArrayAdapter();

        $client = new TMEClient($httpClient, $settings, $cache);

        // First request should trigger the auth call
        $response1 = $client->makeRequest('products/data', ['symbols' => ['M7-DIO']]);
        $this->assertSame(200, $response1->getStatusCode());

        // Second request should use the cached token
        $response2 = $client->makeRequest('products/data', ['symbols' => ['M7-DIO']]);
        $this->assertSame(200, $response2->getStatusCode());

        // Total network requests should be 3 (1 for auth, 2 for the actual requests)
        $this->assertCount(3, $requests);

        // Check the auth request details
        $this->assertSame('https://api.tme.eu/auth/token', $requests[0]['url']);
        $this->assertSame('POST', $requests[0]['method']);
        $this->assertStringContainsString('Authorization: Basic ' . base64_encode('test_token:test_secret'), implode("\n", $requests[0]['options']['headers']));
        $this->assertStringContainsString('grant_type=client_credentials', $requests[0]['options']['body']);

        // Check the action requests verify the cached token was appended
        $this->assertSame('https://api.tme.eu/products/data', explode('?', $requests[1]['url'])[0]);
        $this->assertStringContainsString('Authorization: Bearer fake_token_123', implode("\n", $requests[1]['options']['headers']));
        $this->assertStringContainsString('Authorization: Bearer fake_token_123', implode("\n", $requests[2]['options']['headers']));
    }
}
