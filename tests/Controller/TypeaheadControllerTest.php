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

namespace App\Tests\Controller;

use App\Entity\UserSystem\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests the TypeaheadController JSON endpoints that back autocomplete widgets in the UI.
 */
#[Group('DB')]
#[Group('slow')]
final class TypeaheadControllerTest extends WebTestCase
{
    public static function endpointProvider(): \Generator
    {
        yield 'tags search'                => ['/en/typeahead/tags/search/test'];
        yield 'parameters part search'     => ['/en/typeahead/parameters/part/search/voltage'];
        yield 'parameters category search' => ['/en/typeahead/parameters/category/search/NPN'];
        yield 'builtin resources'          => ['/en/typeahead/builtInResources/search?query=DIP'];
        yield 'parts search'               => ['/en/typeahead/parts/search/res'];
    }

    public static function partsReadEndpointProvider(): \Generator
    {
        // These require @parts.read — noread user must be denied
        yield 'tags search'            => ['/en/typeahead/tags/search/test'];
        yield 'parameters part search' => ['/en/typeahead/parameters/part/search/voltage'];
        yield 'parts search'           => ['/en/typeahead/parts/search/res'];
    }

    private function loginClient(string $username): KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['name' => $username]);
        if ($user === null) {
            $this->markTestSkipped("Fixture user '$username' not found.");
        }
        $client->loginUser($user);
        return $client;
    }

    // -----------------------------------------------------------------------
    // Response format
    // -----------------------------------------------------------------------

    #[DataProvider('endpointProvider')]
    public function testEndpointReturnsSuccessfulJsonForAdmin(string $url): void
    {
        $client = $this->loginClient('admin');
        $client->request('GET', $url);

        $this->assertResponseIsSuccessful();
        $this->assertJson($client->getResponse()->getContent());
    }

    #[DataProvider('endpointProvider')]
    public function testEndpointReturnsJsonArray(string $url): void
    {
        $client = $this->loginClient('admin');
        $client->request('GET', $url);

        $decoded = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($decoded, "Response from $url should be a JSON array");
    }

    // -----------------------------------------------------------------------
    // Tags search: result structure
    // -----------------------------------------------------------------------

    public function testTagsSearchReturnsStrings(): void
    {
        $client = $this->loginClient('admin');
        $client->request('GET', '/en/typeahead/tags/search/a');

        $tags = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($tags);
        foreach ($tags as $tag) {
            $this->assertIsString($tag, 'Each tag entry should be a plain string');
        }
    }

    // -----------------------------------------------------------------------
    // Parts search: result structure
    // -----------------------------------------------------------------------

    public function testPartsSearchReturnsArrayWithExpectedKeys(): void
    {
        $client = $this->loginClient('admin');
        $client->request('GET', '/en/typeahead/parts/search/test');

        $parts = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($parts);
        // Each result must have at least id and name
        foreach ($parts as $part) {
            $this->assertArrayHasKey('id', $part);
            $this->assertArrayHasKey('name', $part);
        }
    }

    // -----------------------------------------------------------------------
    // Access control
    // -----------------------------------------------------------------------

    #[DataProvider('endpointProvider')]
    public function testUnauthenticatedCanAccessTypeahead(string $url): void
    {
        // Anonymous user (readonly group) has @parts.read, so these endpoints return 200.
        $client = static::createClient();
        $client->request('GET', $url);
        $this->assertResponseIsSuccessful();
    }

    #[DataProvider('partsReadEndpointProvider')]
    public function testNoreadUserIsDenied(string $url): void
    {
        $client = $this->loginClient('noread');
        $client->followRedirects(false);
        $client->request('GET', $url);

        $response = $client->getResponse();
        $this->assertTrue(
            $response->getStatusCode() === 403 || $response->isRedirect(),
            "Expected 403 or redirect for noread user on $url, got " . $response->getStatusCode()
        );
    }

    #[DataProvider('endpointProvider')]
    public function testEditorUserCanAccess(string $url): void
    {
        $client = $this->loginClient('user');
        $client->request('GET', $url);

        $this->assertResponseIsSuccessful();
    }
}
