<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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
namespace App\Tests\Services\InfoProviderSystem;

use App\Entity\Parts\InfoProviderReference;
use App\Entity\Parts\Part;
use App\Services\InfoProviderSystem\BulkRefreshResultsBuilder;
use App\Services\InfoProviderSystem\DTOs\ProviderInfoDTO;
use App\Services\InfoProviderSystem\ProviderRegistry;
use App\Services\InfoProviderSystem\Providers\InfoProviderInterface;
use PHPUnit\Framework\TestCase;

final class BulkRefreshResultsBuilderTest extends TestCase
{
    private function builder(bool $provider_active = true): BulkRefreshResultsBuilder
    {
        $provider = $this->createMock(InfoProviderInterface::class);
        $provider->method('getProviderInfo')->willReturn(new ProviderInfoDTO(key: 'test', name: 'Test provider'));
        $provider->method('isActive')->willReturn($provider_active);

        return new BulkRefreshResultsBuilder(new ProviderRegistry([$provider]));
    }

    private function partWithReference(?InfoProviderReference $reference): Part
    {
        $part = new Part();
        $part->setName('Test part');
        $part->setDescription('A part');

        if ($reference !== null) {
            $part->setProviderReference($reference);
        }

        return $part;
    }

    public function testEmptyPartListIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->builder()->build([]);
    }

    public function testPartIsRefreshedFromItsOwnProviderReference(): void
    {
        $part = $this->partWithReference(
            InfoProviderReference::providerReference('test', '1234', 'https://example.com/part/1234')
        );

        $results = $this->builder()->build([$part])->partResults;

        $this->assertCount(1, $results);
        $this->assertSame($part, $results[0]->part);
        $this->assertFalse($results[0]->hasErrors());
        $this->assertCount(1, $results[0]->searchResults);

        //The single result must point at exactly the provider entry the part was created from
        $search_result = $results[0]->searchResults[0]->searchResult;
        $this->assertSame('test', $search_result->provider_key);
        $this->assertSame('1234', $search_result->provider_id);
        $this->assertSame('https://example.com/part/1234', $search_result->provider_url);
    }

    public function testPartWithoutProviderReferenceGetsAnError(): void
    {
        $part = $this->partWithReference(null);

        $results = $this->builder()->build([$part])->partResults;

        $this->assertFalse($results[0]->hasResults());
        $this->assertSame(['info_providers.bulk_refresh.error.no_provider_reference'], $results[0]->errors);
    }

    public function testPartOfAnUnknownProviderGetsAnError(): void
    {
        $part = $this->partWithReference(InfoProviderReference::providerReference('does_not_exist', '1234'));

        $results = $this->builder()->build([$part])->partResults;

        $this->assertFalse($results[0]->hasResults());
        $this->assertSame(['info_providers.bulk_refresh.error.provider_unavailable'], $results[0]->errors);
    }

    public function testPartOfADisabledProviderGetsAnError(): void
    {
        $part = $this->partWithReference(InfoProviderReference::providerReference('test', '1234'));

        $results = $this->builder(provider_active: false)->build([$part])->partResults;

        $this->assertFalse($results[0]->hasResults());
        $this->assertSame(['info_providers.bulk_refresh.error.provider_unavailable'], $results[0]->errors);
    }

    public function testPartsAreKeptInTheGivenOrder(): void
    {
        $refreshable = $this->partWithReference(InfoProviderReference::providerReference('test', '1234'));
        $not_refreshable = $this->partWithReference(null);

        $results = $this->builder()->build([$not_refreshable, $refreshable])->partResults;

        $this->assertSame($not_refreshable, $results[0]->part);
        $this->assertSame($refreshable, $results[1]->part);
    }
}
