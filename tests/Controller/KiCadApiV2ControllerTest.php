<?php
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

declare(strict_types=1);

namespace App\Tests\Controller;

use App\DataFixtures\APITokenFixtures;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class KiCadApiV2ControllerTest extends WebTestCase
{
    private const BASE_URL = '/en/kicad-api/v2';

    protected function createClientWithCredentials(string $token = APITokenFixtures::TOKEN_READONLY): KernelBrowser
    {
        return static::createClient([], ['headers' => ['authorization' => 'Bearer '.$token]]);
    }

    public function testRoot(): void
    {
        $client = $this->createClientWithCredentials();
        $client->request('GET', self::BASE_URL.'/');

        self::assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        self::assertJson($content);

        $array = json_decode($content, true);
        self::assertArrayHasKey('categories', $array);
        self::assertArrayHasKey('parts', $array);
    }

    public function testCategories(): void
    {
        $client = $this->createClientWithCredentials();
        $client->request('GET', self::BASE_URL.'/categories.json');

        self::assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        self::assertJson($content);

        $data = json_decode($content, true);
        self::assertCount(1, $data);

        $category = $data[0];
        self::assertArrayHasKey('name', $category);
        self::assertArrayHasKey('id', $category);
    }

    public function testCategoryParts(): void
    {
        $client = $this->createClientWithCredentials();
        $client->request('GET', self::BASE_URL.'/parts/category/1.json');

        self::assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        self::assertJson($content);

        $data = json_decode($content, true);
        self::assertCount(3, $data);

        $part = $data[0];
        self::assertArrayHasKey('name', $part);
        self::assertArrayHasKey('id', $part);
        self::assertArrayHasKey('description', $part);
    }

    public function testCategoryPartsMinimal(): void
    {
        $client = $this->createClientWithCredentials();
        $client->request('GET', self::BASE_URL.'/parts/category/1.json?minimal=true');

        self::assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        self::assertJson($content);

        $data = json_decode($content, true);
        self::assertCount(3, $data);
    }

    public function testPartDetailsHasVolatileFields(): void
    {
        $client = $this->createClientWithCredentials();
        $client->request('GET', self::BASE_URL.'/parts/1.json');

        self::assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        self::assertJson($content);

        $data = json_decode($content, true);

        // V2 should have volatile flag on Stock field
        self::assertArrayHasKey('fields', $data);
        self::assertArrayHasKey('Stock', $data['fields']);
        self::assertArrayHasKey('volatile', $data['fields']['Stock']);
        self::assertEquals('True', $data['fields']['Stock']['volatile']);
    }

    public function testPartDetailsV2VsV1Difference(): void
    {
        $client = $this->createClientWithCredentials();

        // Get v1 response
        $client->request('GET', '/en/kicad-api/v1/parts/1.json');
        self::assertResponseIsSuccessful();
        $v1Data = json_decode($client->getResponse()->getContent(), true);

        // Get v2 response
        $client->request('GET', self::BASE_URL.'/parts/1.json');
        self::assertResponseIsSuccessful();
        $v2Data = json_decode($client->getResponse()->getContent(), true);

        // V1 should NOT have volatile on Stock
        self::assertArrayNotHasKey('volatile', $v1Data['fields']['Stock']);

        // V2 should have volatile on Stock
        self::assertArrayHasKey('volatile', $v2Data['fields']['Stock']);

        // Both should have the same stock value
        self::assertEquals($v1Data['fields']['Stock']['value'], $v2Data['fields']['Stock']['value']);
    }

    public function testCategoriesHasCacheHeaders(): void
    {
        $client = $this->createClientWithCredentials();
        $client->request('GET', self::BASE_URL.'/categories.json');

        self::assertResponseIsSuccessful();
        $response = $client->getResponse();
        self::assertNotNull($response->headers->get('ETag'));
        self::assertStringContainsString('max-age=', $response->headers->get('Cache-Control'));
    }

    public function testConditionalRequestReturns304(): void
    {
        $client = $this->createClientWithCredentials();
        $client->request('GET', self::BASE_URL.'/categories.json');

        $etag = $client->getResponse()->headers->get('ETag');
        self::assertNotNull($etag);

        $client->request('GET', self::BASE_URL.'/categories.json', [], [], [
            'HTTP_IF_NONE_MATCH' => $etag,
        ]);

        self::assertResponseStatusCodeSame(304);
    }

    public function testUnauthenticatedAccessDenied(): void
    {
        $client = static::createClient();
        $client->request('GET', self::BASE_URL.'/categories.json');

        // Anonymous user has default read permissions in Part-DB,
        // so this returns 200 rather than a redirect
        self::assertResponseIsSuccessful();
    }
}
