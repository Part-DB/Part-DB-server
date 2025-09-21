<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2025 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Tests\Services\InfoProviderSystem\DTOs;

use App\Entity\Parts\Part;
use App\Services\InfoProviderSystem\DTOs\BulkSearchResponseDTO;
use App\Services\InfoProviderSystem\DTOs\BulkSearchPartResultDTO;
use App\Services\InfoProviderSystem\DTOs\BulkSearchPartResultsDTO;
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class BulkSearchResponseDTOTest extends KernelTestCase
{

    private EntityManagerInterface $entityManager;

    private BulkSearchResponseDTO $dummyEmpty;
    private BulkSearchResponseDTO $dummy;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $this->dummyEmpty = new BulkSearchResponseDTO(partResults: []);
        $this->dummy = new BulkSearchResponseDTO(partResults: [
            new BulkSearchPartResultsDTO(
                part: $this->entityManager->find(Part::class, 1),
                searchResults: [
                    new BulkSearchPartResultDTO(
                        searchResult: new SearchResultDTO(provider_key: "dummy", provider_id: "1234", name: "Test Part", description: "A part for testing"),
                        sourceField: "mpn", sourceKeyword: "1234", priority: 1
                    ),
                    new BulkSearchPartResultDTO(
                        searchResult: new SearchResultDTO(provider_key: "test", provider_id: "test", name: "Test Part2", description: "A part for testing"),
                        sourceField: "name", sourceKeyword: "1234",
                        localPart: $this->entityManager->find(Part::class, 2), priority: 2,
                    ),
                ],
                errors: ['Error 1']
            )
        ]);
    }

    public function testSerializationBackAndForthEmpty(): void
    {
        $serialized = $this->dummyEmpty->toSerializableRepresentation();
        //Ensure that it is json_encodable
        $json = json_encode($serialized, JSON_THROW_ON_ERROR);
        $this->assertJson($json);
        $deserialized = BulkSearchResponseDTO::fromSerializableRepresentation(json_decode($json), $this->entityManager);

        $this->assertEquals($this->dummyEmpty, $deserialized);
    }

    public function testSerializationBackAndForth(): void
    {
        $serialized = $this->dummy->toSerializableRepresentation();
        //Ensure that it is json_encodable
        $this->assertJson(json_encode($serialized, JSON_THROW_ON_ERROR));
        $deserialized = BulkSearchResponseDTO::fromSerializableRepresentation($serialized, $this->entityManager);

        $this->assertEquals($this->dummy, $deserialized);
    }

    public function testToSerializableRepresentation(): void
    {
        $serialized = $this->dummy->toSerializableRepresentation();

        $expected = array (
            0 =>
                array (
                    'part_id' => 1,
                    'search_results' =>
                        array (
                            0 =>
                                array (
                                    'dto' =>
                                        array (
                                            'provider_key' => 'dummy',
                                            'provider_id' => '1234',
                                            'name' => 'Test Part',
                                            'description' => 'A part for testing',
                                            'category' => NULL,
                                            'manufacturer' => NULL,
                                            'mpn' => NULL,
                                            'preview_image_url' => NULL,
                                            'manufacturing_status' => NULL,
                                            'provider_url' => NULL,
                                            'footprint' => NULL,
                                        ),
                                    'source_field' => 'mpn',
                                    'source_keyword' => '1234',
                                    'localPart' => NULL,
                                    'priority' => 1,
                                ),
                            1 =>
                                array (
                                    'dto' =>
                                        array (
                                            'provider_key' => 'test',
                                            'provider_id' => 'test',
                                            'name' => 'Test Part2',
                                            'description' => 'A part for testing',
                                            'category' => NULL,
                                            'manufacturer' => NULL,
                                            'mpn' => NULL,
                                            'preview_image_url' => NULL,
                                            'manufacturing_status' => NULL,
                                            'provider_url' => NULL,
                                            'footprint' => NULL,
                                        ),
                                    'source_field' => 'name',
                                    'source_keyword' => '1234',
                                    'localPart' => 2,
                                    'priority' => 2,
                                ),
                        ),
                    'errors' =>
                        array (
                            0 => 'Error 1',
                        ),
                ),
        );

        $this->assertEquals($expected, $serialized);
    }

    public function testFromSerializableRepresentation(): void
    {
        $input = array (
            0 =>
                array (
                    'part_id' => 1,
                    'search_results' =>
                        array (
                            0 =>
                                array (
                                    'dto' =>
                                        array (
                                            'provider_key' => 'dummy',
                                            'provider_id' => '1234',
                                            'name' => 'Test Part',
                                            'description' => 'A part for testing',
                                            'category' => NULL,
                                            'manufacturer' => NULL,
                                            'mpn' => NULL,
                                            'preview_image_url' => NULL,
                                            'manufacturing_status' => NULL,
                                            'provider_url' => NULL,
                                            'footprint' => NULL,
                                        ),
                                    'source_field' => 'mpn',
                                    'source_keyword' => '1234',
                                    'localPart' => NULL,
                                    'priority' => 1,
                                ),
                            1 =>
                                array (
                                    'dto' =>
                                        array (
                                            'provider_key' => 'test',
                                            'provider_id' => 'test',
                                            'name' => 'Test Part2',
                                            'description' => 'A part for testing',
                                            'category' => NULL,
                                            'manufacturer' => NULL,
                                            'mpn' => NULL,
                                            'preview_image_url' => NULL,
                                            'manufacturing_status' => NULL,
                                            'provider_url' => NULL,
                                            'footprint' => NULL,
                                        ),
                                    'source_field' => 'name',
                                    'source_keyword' => '1234',
                                    'localPart' => 2,
                                    'priority' => 2,
                                ),
                        ),
                    'errors' =>
                        array (
                            0 => 'Error 1',
                        ),
                ),
        );

        $deserialized = BulkSearchResponseDTO::fromSerializableRepresentation($input, $this->entityManager);
        $this->assertEquals($this->dummy, $deserialized);
    }

    public function testMerge(): void
    {
        $merged = BulkSearchResponseDTO::merge($this->dummy, $this->dummyEmpty);
        $this->assertCount(1, $merged->partResults);

        $merged = BulkSearchResponseDTO::merge($this->dummyEmpty, $this->dummyEmpty);
        $this->assertCount(0, $merged->partResults);

        $merged = BulkSearchResponseDTO::merge($this->dummy, $this->dummy, $this->dummy);
        $this->assertCount(3, $merged->partResults);
    }

    public function testReplaceResultsForPart(): void
    {
        $newPartResults = new BulkSearchPartResultsDTO(
            part: $this->entityManager->find(Part::class, 1),
            searchResults: [
                new BulkSearchPartResultDTO(
                    searchResult: new SearchResultDTO(provider_key: "new", provider_id: "new", name: "New Part", description: "A new part"),
                    sourceField: "mpn", sourceKeyword: "new", priority: 1
                )
            ],
            errors: ['New Error']
        );

        $replaced = $this->dummy->replaceResultsForPart($newPartResults);
        $this->assertCount(1, $replaced->partResults);
        $this->assertSame($newPartResults, $replaced->partResults[0]);
    }

    public function testReplaceResultsForPartNotExisting(): void
    {
        $newPartResults = new BulkSearchPartResultsDTO(
            part: $this->entityManager->find(Part::class, 1),
            searchResults: [
                new BulkSearchPartResultDTO(
                    searchResult: new SearchResultDTO(provider_key: "new", provider_id: "new", name: "New Part", description: "A new part"),
                    sourceField: "mpn", sourceKeyword: "new", priority: 1
                )
            ],
            errors: ['New Error']
        );

        $this->expectException(\InvalidArgumentException::class);

        $replaced = $this->dummyEmpty->replaceResultsForPart($newPartResults);
    }
}
