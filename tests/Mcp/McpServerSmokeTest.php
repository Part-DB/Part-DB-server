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

namespace App\Tests\Mcp;

use App\DataFixtures\APITokenFixtures;
use App\Settings\AISettings\McpSettings;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Smoke tests for the MCP HTTP endpoint: checks that the server is reachable, the JSON-RPC handshake works,
 * tools are registered and a simple tool can be called end-to-end. Individual tool behavior is covered by the
 * processor tests in tests/State/Mcp.
 */
final class McpServerSmokeTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        //Keep the same kernel (and therefore the same settings and MCP session store) across all requests of a test
        $this->client->disableReboot();

        //The MCP endpoint is disabled by default, enable it for this test
        self::getContainer()->get(McpSettings::class)->enabled = true;
    }

    /**
     * Sends a JSON-RPC request to the MCP endpoint and returns the raw response.
     */
    private function sendRpc(string $method, array $params = [], ?string $sessionId = null, ?string $token = APITokenFixtures::TOKEN_ADMIN, int $id = 1): void
    {
        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json, text/event-stream',
        ];
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        if (null !== $sessionId) {
            $server['HTTP_MCP_SESSION_ID'] = $sessionId;
        }

        $payload = ['jsonrpc' => '2.0', 'id' => $id, 'method' => $method];
        if ([] !== $params) {
            $payload['params'] = $params;
        }

        $this->client->request('POST', '/mcp', server: $server, content: json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * Decodes the JSON-RPC response (plain JSON or a single SSE "data:" event).
     */
    private function decodeResponse(): array
    {
        $content = (string) $this->client->getResponse()->getContent();
        if (preg_match('/^data:\s*(.+)$/m', $content, $matches)) {
            $content = $matches[1];
        }

        return json_decode($content, true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Performs the initialize handshake and returns the session ID.
     */
    private function initialize(): string
    {
        $this->sendRpc('initialize', [
            'protocolVersion' => '2025-06-18',
            'capabilities' => new \stdClass(),
            'clientInfo' => ['name' => 'part-db-smoke-test', 'version' => '1.0'],
        ]);

        self::assertResponseIsSuccessful();
        $sessionId = $this->client->getResponse()->headers->get('Mcp-Session-Id');
        self::assertNotEmpty($sessionId, 'The MCP server should return a session ID on initialize');

        $data = $this->decodeResponse();
        self::assertSame('Part-DB', $data['result']['serverInfo']['name'] ?? null);
        self::assertArrayHasKey('tools', $data['result']['capabilities'] ?? []);

        return $sessionId;
    }

    public function testUnauthenticatedAccessIsDenied(): void
    {
        $this->sendRpc('initialize', [
            'protocolVersion' => '2025-06-18',
            'capabilities' => new \stdClass(),
            'clientInfo' => ['name' => 'part-db-smoke-test', 'version' => '1.0'],
        ], token: null);

        self::assertResponseStatusCodeSame(401);
    }

    public function testInitialize(): void
    {
        $this->initialize();
    }

    public function testToolsListContainsPartTools(): void
    {
        $sessionId = $this->initialize();

        $this->sendRpc('tools/list', sessionId: $sessionId, id: 2);
        self::assertResponseIsSuccessful();

        $data = $this->decodeResponse();
        $toolNames = array_column($data['result']['tools'] ?? [], 'name');

        self::assertContains('search_parts', $toolNames);
        self::assertContains('get_part_details', $toolNames);
        self::assertContains('list_categories', $toolNames);
    }

    public function testCallListCategoriesTool(): void
    {
        $sessionId = $this->initialize();

        $this->sendRpc('tools/call', [
            'name' => 'list_categories',
            'arguments' => new \stdClass(),
        ], sessionId: $sessionId, id: 3);
        self::assertResponseIsSuccessful();

        $data = $this->decodeResponse();
        self::assertArrayNotHasKey('error', $data);
        self::assertFalse($data['result']['isError'] ?? false, 'Tool call should not return an error: '.json_encode($data));
        self::assertNotEmpty($data['result']['content'] ?? []);
    }
}
