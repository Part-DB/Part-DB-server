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

class KiCadApiControllerTest extends WebTestCase
{
    private const BASE_URL = '/en/kicad-api/v1';

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

        //Check if the response contains the expected keys
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
        //There should be only one category, as the other ones contain no parts
        self::assertCount(1, $data);

        //Check if the response contains the expected keys
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
        //There should be 3 parts in the category
        self::assertCount(3, $data);

        //Check if the response contains the expected keys
        $part = $data[0];
        self::assertArrayHasKey('name', $part);
        self::assertArrayHasKey('id', $part);
        self::assertArrayHasKey('description', $part);
    }

    public function testCategoryPartsForEmptyCategory(): void
    {
        $client = $this->createClientWithCredentials();
        $client->request('GET', self::BASE_URL.'/parts/category/2.json');

        //Response should still be successful, but the result should be empty
        self::assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        self::assertJson($content);
        self::assertEmpty(json_decode($content, true));
    }

    public function testPartDetailsPart1(): void
    {
        $client = $this->createClientWithCredentials();
        $client->request('GET', self::BASE_URL.'/parts/1.json');

        //Response should still be successful, but the result should be empty
        self::assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        self::assertJson($content);

        $data = json_decode($content, true);

        $expected = array(
            'id' => '1',
            'name' => 'Part 1',
            'symbolIdStr' => 'Part:1',
            'exclude_from_bom' => 'False',
            'exclude_from_board' => 'True',
            'exclude_from_sim' => 'False',
            'fields' =>
                array(
                    'footprint' =>
                        array(
                            'value' => 'Part:1',
                            'visible' => 'False',
                        ),
                    'reference' =>
                        array(
                            'value' => 'P',
                            'visible' => 'True',
                        ),
                    'value' =>
                        array(
                            'value' => 'Part 1',
                            'visible' => 'True',
                        ),
                    'keywords' =>
                        array(
                            'value' => '',
                            'visible' => 'False',
                        ),
                    'datasheet' =>
                        array(
                            'value' => 'http://localhost/en/part/1/info',
                            'visible' => 'False',
                        ),
                    'Part-DB URL' =>
                        array(
                            'value' => 'http://localhost/en/part/1/info',
                            'visible' => 'False',
                        ),
                    'description' =>
                        array(
                            'value' => '',
                            'visible' => 'False',
                        ),
                    'Category' =>
                        array(
                            'value' => 'Node 1',
                            'visible' => 'False',
                        ),
                    'Manufacturing Status' =>
                        array(
                            'value' => '',
                            'visible' => 'False',
                        ),
                    'Part-DB ID' =>
                        array(
                            'value' => '1',
                            'visible' => 'False',
                        ),
                    'Stock' =>
                        array(
                            'value' => '0',
                            'visible' => 'False',
                        ),
                ),
        );

        self::assertEquals($expected, $data);
    }

    public function testPartDetailsPart2(): void
    {
        $client = $this->createClientWithCredentials();
        $client->request('GET', self::BASE_URL.'/parts/2.json');

        self::assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        self::assertJson($content);

        $data = json_decode($content, true);

        //For part 2, EDA info should be inherited from category and footprint (no part-level overrides)
        $expected = array (
            'id' => '2',
            'name' => 'Part 2',
            'symbolIdStr' => 'Category:1',
            'exclude_from_bom' => 'False',
            'exclude_from_board' => 'True',
            'exclude_from_sim' => 'False',
            'fields' =>
                array (
                    'footprint' =>
                        array (
                            'value' => 'Footprint:1',
                            'visible' => 'False',
                        ),
                    'reference' =>
                        array (
                            'value' => 'C',
                            'visible' => 'True',
                        ),
                    'value' =>
                        array (
                            'value' => 'Part 2',
                            'visible' => 'True',
                        ),
                    'keywords' =>
                        array (
                            'value' => 'test, Test, Part2',
                            'visible' => 'False',
                        ),
                    'datasheet' =>
                        array (
                            'value' => 'http://localhost/en/part/2/info',
                            'visible' => 'False',
                        ),
                    'Part-DB URL' =>
                        array (
                            'value' => 'http://localhost/en/part/2/info',
                            'visible' => 'False',
                        ),
                    'description' =>
                        array (
                            'value' => '',
                            'visible' => 'False',
                        ),
                    'Category' =>
                        array (
                            'value' => 'Node 1',
                            'visible' => 'False',
                        ),
                    'Manufacturer' =>
                        array (
                            'value' => 'Node 1',
                            'visible' => 'False',
                        ),
                    'Manufacturing Status' =>
                        array (
                            'value' => 'Active',
                            'visible' => 'False',
                        ),
                    'Part-DB Footprint' =>
                        array (
                            'value' => 'Node 1',
                            'visible' => 'False',
                        ),
                    'Mass' =>
                        array (
                            'value' => '100.2 g',
                            'visible' => 'False',
                        ),
                    'Part-DB ID' =>
                        array (
                            'value' => '2',
                            'visible' => 'False',
                        ),
                    'Part-DB IPN' =>
                        array (
                            'value' => 'IPN123',
                            'visible' => 'False',
                        ),
                    'manf' =>
                        array (
                            'value' => 'Node 1',
                            'visible' => 'False',
                        ),
                    'Stock' =>
                        array (
                            'value' => '0',
                            'visible' => 'False',
                        ),
                ),
        );

        self::assertEquals($expected, $data);
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

        //Make a conditional request with the ETag
        $client->request('GET', self::BASE_URL.'/categories.json', [], [], [
            'HTTP_IF_NONE_MATCH' => $etag,
        ]);

        self::assertResponseStatusCodeSame(304);
    }

}