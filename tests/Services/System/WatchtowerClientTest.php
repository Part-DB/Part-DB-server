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

namespace App\Tests\Services\System;

use App\Services\System\WatchtowerClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class WatchtowerClientTest extends TestCase
{
    private function createClient(string $url = 'http://watchtower:8080', string $token = 'test-token', ?HttpClientInterface $httpClient = null): WatchtowerClient
    {
        return new WatchtowerClient(
            $httpClient ?? $this->createMock(HttpClientInterface::class),
            new NullLogger(),
            $url,
            $token,
        );
    }

    public function testIsConfiguredReturnsTrueWhenBothSet(): void
    {
        $client = $this->createClient('http://watchtower:8080', 'my-token');
        $this->assertTrue($client->isConfigured());
    }

    public function testIsConfiguredReturnsFalseWhenUrlEmpty(): void
    {
        $client = $this->createClient('', 'my-token');
        $this->assertFalse($client->isConfigured());
    }

    public function testIsConfiguredReturnsFalseWhenTokenEmpty(): void
    {
        $client = $this->createClient('http://watchtower:8080', '');
        $this->assertFalse($client->isConfigured());
    }

    public function testIsConfiguredReturnsFalseWhenBothEmpty(): void
    {
        $client = $this->createClient('', '');
        $this->assertFalse($client->isConfigured());
    }

    public function testIsAvailableReturnsFalseWhenNotConfigured(): void
    {
        $client = $this->createClient('', '');
        $this->assertFalse($client->isAvailable());
    }

    public function testIsAvailableReturnsTrueOnSuccessResponse(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'http://watchtower:8080/v1/update', $this->callback(function (array $options) {
                return $options['headers']['Authorization'] === 'Bearer test-token'
                    && $options['timeout'] === 3;
            }))
            ->willReturn($response);

        $client = $this->createClient('http://watchtower:8080', 'test-token', $httpClient);
        $this->assertTrue($client->isAvailable());
    }

    public function testIsAvailableReturnsTrueOn401(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(401);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $client = $this->createClient('http://watchtower:8080', 'test-token', $httpClient);
        $this->assertTrue($client->isAvailable());
    }

    public function testIsAvailableReturnsFalseOn500(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(500);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $client = $this->createClient('http://watchtower:8080', 'test-token', $httpClient);
        $this->assertFalse($client->isAvailable());
    }

    public function testIsAvailableReturnsFalseOnException(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willThrowException(new \RuntimeException('Connection refused'));

        $client = $this->createClient('http://watchtower:8080', 'test-token', $httpClient);
        $this->assertFalse($client->isAvailable());
    }

    public function testTriggerUpdateThrowsWhenNotConfigured(): void
    {
        $client = $this->createClient('', '');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Watchtower is not configured');
        $client->triggerUpdate();
    }

    public function testTriggerUpdateReturnsTrueOnSuccess(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with('POST', 'http://watchtower:8080/v1/update', $this->callback(function (array $options) {
                return $options['headers']['Authorization'] === 'Bearer test-token'
                    && $options['timeout'] === 10;
            }))
            ->willReturn($response);

        $client = $this->createClient('http://watchtower:8080', 'test-token', $httpClient);
        $this->assertTrue($client->triggerUpdate());
    }

    public function testTriggerUpdateReturnsTrueOn202(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(202);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $client = $this->createClient('http://watchtower:8080', 'test-token', $httpClient);
        $this->assertTrue($client->triggerUpdate());
    }

    public function testTriggerUpdateReturnsFalseOnServerError(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(500);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $client = $this->createClient('http://watchtower:8080', 'test-token', $httpClient);
        $this->assertFalse($client->triggerUpdate());
    }

    public function testTriggerUpdateReturnsFalseOnException(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willThrowException(new \RuntimeException('Network error'));

        $client = $this->createClient('http://watchtower:8080', 'test-token', $httpClient);
        $this->assertFalse($client->triggerUpdate());
    }

    public function testUrlTrailingSlashIsNormalized(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'http://watchtower:8080/v1/update', $this->anything())
            ->willReturn($response);

        $client = $this->createClient('http://watchtower:8080/', 'test-token', $httpClient);
        $client->isAvailable();
    }
}
