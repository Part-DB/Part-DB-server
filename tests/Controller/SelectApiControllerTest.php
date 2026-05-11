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
 * Tests the SelectAPIController endpoints used by select2 widgets.
 * These JSON endpoints back every structural-entity dropdown in the UI.
 */
#[Group('DB')]
#[Group('slow')]
final class SelectApiControllerTest extends WebTestCase
{
    public static function endpointProvider(): \Generator
    {
        yield 'category'         => ['/en/select_api/category'];
        yield 'footprint'        => ['/en/select_api/footprint'];
        yield 'manufacturer'     => ['/en/select_api/manufacturer'];
        yield 'measurement_unit' => ['/en/select_api/measurement_unit'];
        yield 'project'          => ['/en/select_api/project'];
        yield 'storage_location' => ['/en/select_api/storage_location'];
        yield 'label_profiles'   => ['/en/select_api/label_profiles'];
    }

    private function adminClient(): KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $admin = $em->getRepository(User::class)->findOneBy(['name' => 'admin']);
        if ($admin === null) {
            $this->markTestSkipped('Fixture user admin not found.');
        }
        $client->loginUser($admin);
        return $client;
    }

    // -----------------------------------------------------------------------
    // Response format
    // -----------------------------------------------------------------------

    #[DataProvider('endpointProvider')]
    public function testEndpointReturns200WithJsonContentType(string $url): void
    {
        $client = $this->adminClient();
        $client->request('GET', $url);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
    }

    #[DataProvider('endpointProvider')]
    public function testEndpointReturnsValidJsonArray(string $url): void
    {
        $client = $this->adminClient();
        $client->request('GET', $url);

        $body = $client->getResponse()->getContent();
        $decoded = json_decode($body, true);

        $this->assertIsArray($decoded, "Response from $url is not a valid JSON array");
    }

    #[DataProvider('endpointProvider')]
    public function testEachEntryHasTextAndValueKeys(string $url): void
    {
        $client = $this->adminClient();
        $client->request('GET', $url);

        $decoded = json_decode($client->getResponse()->getContent(), true);
        // Some endpoints include an empty "select none" entry at index 0; all entries must have text + value
        foreach ($decoded as $entry) {
            $this->assertArrayHasKey('text', $entry, "Entry in $url missing 'text' key");
            $this->assertArrayHasKey('value', $entry, "Entry in $url missing 'value' key");
        }
    }

    // -----------------------------------------------------------------------
    // Access control
    // -----------------------------------------------------------------------

    #[DataProvider('endpointProvider')]
    public function testUnauthenticatedCanReadSelectApi(string $url): void
    {
        // The anonymous user (readonly group) has read access to structural entities,
        // so these endpoints return 200 even without a session.
        $client = static::createClient();
        $client->request('GET', $url);
        $this->assertResponseIsSuccessful();
    }

    #[DataProvider('endpointProvider')]
    public function testNoreadUserIsDenied(string $url): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $noread = $em->getRepository(User::class)->findOneBy(['name' => 'noread']);
        if ($noread === null) {
            $this->markTestSkipped('Fixture user noread not found.');
        }
        $client->loginUser($noread);
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
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['name' => 'user']);
        if ($user === null) {
            $this->markTestSkipped('Fixture user user not found.');
        }
        $client->loginUser($user);
        $client->request('GET', $url);

        $this->assertResponseIsSuccessful();
    }
}
