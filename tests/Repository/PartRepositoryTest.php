<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use App\Settings\MiscSettings\IpnSuggestSettings;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\TestCase;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Symfony\Contracts\Translation\TranslatorInterface;
use App\Repository\PartRepository;

final class PartRepositoryTest extends TestCase
{
    public function test_autocompleteSearch_builds_expected_query_without_db(): void
    {
        $qb = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'select', 'leftJoin', 'where', 'orWhere',
                'setParameter', 'setMaxResults', 'orderBy', 'getQuery'
            ])->getMock();

        $qb->expects(self::once())->method('select')->with('part')->willReturnSelf();

        $qb->expects(self::exactly(2))->method('leftJoin')->with($this->anything(), $this->anything())->willReturnSelf();

        $qb->expects(self::atLeastOnce())->method('where')->with($this->anything())->willReturnSelf();
        $qb->method('orWhere')->with($this->anything())->willReturnSelf();

        $searchQuery = 'res';
        $qb->expects(self::once())->method('setParameter')->with('query', '%'.$searchQuery.'%')->willReturnSelf();
        $qb->expects(self::once())->method('setMaxResults')->with(10)->willReturnSelf();
        $qb->expects(self::once())->method('orderBy')->with('NATSORT(part.name)', 'ASC')->willReturnSelf();

        $emMock = $this->createMock(EntityManagerInterface::class);
        $classMetadata = new ClassMetadata(Part::class);
        $emMock->method('getClassMetadata')->with(Part::class)->willReturn($classMetadata);

        $translatorMock = $this->createMock(TranslatorInterface::class);
        $ipnSuggestSettings = $this->createMock(IpnSuggestSettings::class);

        $repo = $this->getMockBuilder(PartRepository::class)
            ->setConstructorArgs([$emMock, $translatorMock, $ipnSuggestSettings])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $repo->expects(self::once())
            ->method('createQueryBuilder')
            ->with('part')
            ->willReturn($qb);

        $part = new Part(); // create found part, because it is not saved in DB
        $part->setName('Resistor');

        $queryMock = $this->getMockBuilder(Query::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getResult'])
            ->getMock();
        $queryMock->expects(self::once())->method('getResult')->willReturn([$part]);

        $qb->method('getQuery')->willReturn($queryMock);

        $result = $repo->autocompleteSearch($searchQuery, 10);

        // Check one part found and returned
        self::assertIsArray($result);
        self::assertCount(1, $result);
        self::assertSame($part, $result[0]);
    }

    public function test_autoCompleteIpn_with_unsaved_part_and_category_without_part_description(): void
    {
        $qb = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'select', 'leftJoin', 'where', 'andWhere', 'orWhere',
                'setParameter', 'setMaxResults', 'orderBy', 'getQuery'
            ])->getMock();

        $qb->method('select')->willReturnSelf();
        $qb->method('leftJoin')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('orWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();

        $emMock = $this->createMock(EntityManagerInterface::class);
        $classMetadata = new ClassMetadata(Part::class);
        $emMock->method('getClassMetadata')->with(Part::class)->willReturn($classMetadata);

        $translatorMock = $this->createMock(TranslatorInterface::class);
        $translatorMock->method('trans')
            ->willReturnCallback(static function (string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string {
                return $id;
            });

        $ipnSuggestSettings = $this->createMock(IpnSuggestSettings::class);

        $ipnSuggestSettings->suggestPartDigits = 4;
        $ipnSuggestSettings->useDuplicateDescription = false;

        $repo = $this->getMockBuilder(PartRepository::class)
            ->setConstructorArgs([$emMock, $translatorMock, $ipnSuggestSettings])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $repo->expects(self::atLeastOnce())
            ->method('createQueryBuilder')
            ->with('part')
            ->willReturn($qb);

        $queryMock = $this->getMockBuilder(Query::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getResult'])
            ->getMock();

        $categoryParent = new Category();
        $categoryParent->setName('Passive components');
        $categoryParent->setPartIpnPrefix('PCOM');

        $categoryChild = new Category();
        $categoryChild->setName('Resistors');
        $categoryChild->setPartIpnPrefix('RES');
        $categoryChild->setParent($categoryParent);

        $partForSuggestGeneration = new Part(); // create found part, because it is not saved in DB
        $partForSuggestGeneration->setIpn('RES-0001');
        $partForSuggestGeneration->setCategory($categoryChild);

        $queryMock->method('getResult')->willReturn([$partForSuggestGeneration]);
        $qb->method('getQuery')->willReturn($queryMock);
        $suggestions = $repo->autoCompleteIpn($partForSuggestGeneration, '', 4);

        // Check structure available
        self::assertIsArray($suggestions);
        self::assertArrayHasKey('commonPrefixes', $suggestions);
        self::assertArrayHasKey('prefixesPartIncrement', $suggestions);
        self::assertNotEmpty($suggestions['commonPrefixes']);
        self::assertNotEmpty($suggestions['prefixesPartIncrement']);

        // Check expected values
        self::assertSame('RES-', $suggestions['commonPrefixes'][0]['title']);
        self::assertSame('part.edit.tab.advanced.ipn.prefix.direct_category', $suggestions['commonPrefixes'][0]['description']);
        self::assertSame('PCOM-RES-', $suggestions['commonPrefixes'][1]['title']);
        self::assertSame('part.edit.tab.advanced.ipn.prefix.hierarchical.no_increment', $suggestions['commonPrefixes'][1]['description']);

        self::assertSame('RES-0002', $suggestions['prefixesPartIncrement'][0]['title']); // next possible free increment for given part category
        self::assertSame('part.edit.tab.advanced.ipn.prefix.direct_category.increment', $suggestions['prefixesPartIncrement'][0]['description']);
        self::assertSame('PCOM-RES-0002', $suggestions['prefixesPartIncrement'][1]['title']); // next possible free increment for given part category
        self::assertSame('part.edit.tab.advanced.ipn.prefix.hierarchical.increment', $suggestions['prefixesPartIncrement'][1]['description']);
    }

    public function test_autoCompleteIpn_with_unsaved_part_and_category_with_part_description(): void
    {
        $qb = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'select', 'leftJoin', 'where', 'andWhere', 'orWhere',
                'setParameter', 'setMaxResults', 'orderBy', 'getQuery'
            ])->getMock();

        $qb->method('select')->willReturnSelf();
        $qb->method('leftJoin')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('orWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();

        $emMock = $this->createMock(EntityManagerInterface::class);
        $classMetadata = new ClassMetadata(Part::class);
        $emMock->method('getClassMetadata')->with(Part::class)->willReturn($classMetadata);

        $translatorMock = $this->createMock(TranslatorInterface::class);
        $translatorMock->method('trans')
            ->willReturnCallback(static function (string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string {
                return $id;
            });

        $ipnSuggestSettings = $this->createMock(IpnSuggestSettings::class);

        $ipnSuggestSettings->suggestPartDigits = 4;
        $ipnSuggestSettings->useDuplicateDescription = false;

        $repo = $this->getMockBuilder(PartRepository::class)
            ->setConstructorArgs([$emMock, $translatorMock, $ipnSuggestSettings])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $repo->expects(self::atLeastOnce())
            ->method('createQueryBuilder')
            ->with('part')
            ->willReturn($qb);

        $queryMock = $this->getMockBuilder(Query::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getResult'])
            ->getMock();

        $categoryParent = new Category();
        $categoryParent->setName('Passive components');
        $categoryParent->setPartIpnPrefix('PCOM');

        $categoryChild = new Category();
        $categoryChild->setName('Resistors');
        $categoryChild->setPartIpnPrefix('RES');
        $categoryChild->setParent($categoryParent);

        $partForSuggestGeneration = new Part(); // create found part, because it is not saved in DB
        $partForSuggestGeneration->setCategory($categoryChild);
        $partForSuggestGeneration->setIpn('1810-1679_1');
        $partForSuggestGeneration->setDescription('NETWORK-RESISTOR 4 0 OHM +5PCT 0.063W TKF SMT');

        $queryMock->method('getResult')->willReturn([$partForSuggestGeneration]);
        $qb->method('getQuery')->willReturn($queryMock);
        $suggestions = $repo->autoCompleteIpn($partForSuggestGeneration, 'NETWORK-RESISTOR 4 0 OHM +5PCT 0.063W TKF SMT', 4);

        // Check structure available
        self::assertIsArray($suggestions);
        self::assertArrayHasKey('commonPrefixes', $suggestions);
        self::assertArrayHasKey('prefixesPartIncrement', $suggestions);
        self::assertNotEmpty($suggestions['commonPrefixes']);
        self::assertCount(2, $suggestions['commonPrefixes']);
        self::assertNotEmpty($suggestions['prefixesPartIncrement']);
        self::assertCount(2, $suggestions['prefixesPartIncrement']);

        // Check expected values without any increment, for user to decide
        self::assertSame('RES-', $suggestions['commonPrefixes'][0]['title']);
        self::assertSame('part.edit.tab.advanced.ipn.prefix.direct_category', $suggestions['commonPrefixes'][0]['description']);
        self::assertSame('PCOM-RES-', $suggestions['commonPrefixes'][1]['title']);
        self::assertSame('part.edit.tab.advanced.ipn.prefix.hierarchical.no_increment', $suggestions['commonPrefixes'][1]['description']);

        // Check expected values with next possible increment at category level
        self::assertSame('RES-0001', $suggestions['prefixesPartIncrement'][0]['title']); // next possible free increment for given part category
        self::assertSame('part.edit.tab.advanced.ipn.prefix.direct_category.increment', $suggestions['prefixesPartIncrement'][0]['description']);
        self::assertSame('PCOM-RES-0001', $suggestions['prefixesPartIncrement'][1]['title']); // next possible free increment for given part category
        self::assertSame('part.edit.tab.advanced.ipn.prefix.hierarchical.increment', $suggestions['prefixesPartIncrement'][1]['description']);

        $ipnSuggestSettings->useDuplicateDescription = true;

        $suggestionsWithSameDescription = $repo->autoCompleteIpn($partForSuggestGeneration, 'NETWORK-RESISTOR 4 0 OHM +5PCT 0.063W TKF SMT', 4);

        // Check structure available
        self::assertIsArray($suggestionsWithSameDescription);
        self::assertArrayHasKey('commonPrefixes', $suggestionsWithSameDescription);
        self::assertArrayHasKey('prefixesPartIncrement', $suggestionsWithSameDescription);
        self::assertNotEmpty($suggestionsWithSameDescription['commonPrefixes']);
        self::assertCount(2, $suggestionsWithSameDescription['commonPrefixes']);
        self::assertNotEmpty($suggestionsWithSameDescription['prefixesPartIncrement']);
        self::assertCount(4, $suggestionsWithSameDescription['prefixesPartIncrement']);

        // Check expected values without any increment, for user to decide
        self::assertSame('RES-', $suggestionsWithSameDescription['commonPrefixes'][0]['title']);
        self::assertSame('part.edit.tab.advanced.ipn.prefix.direct_category', $suggestionsWithSameDescription['commonPrefixes'][0]['description']);
        self::assertSame('PCOM-RES-', $suggestionsWithSameDescription['commonPrefixes'][1]['title']);
        self::assertSame('part.edit.tab.advanced.ipn.prefix.hierarchical.no_increment', $suggestionsWithSameDescription['commonPrefixes'][1]['description']);

        // Check expected values with next possible increment at part description level
        self::assertSame('1810-1679_1', $suggestionsWithSameDescription['prefixesPartIncrement'][0]['title']); // current given value
        self::assertSame('part.edit.tab.advanced.ipn.prefix.description.current-increment', $suggestionsWithSameDescription['prefixesPartIncrement'][0]['description']);
        self::assertSame('1810-1679_2', $suggestionsWithSameDescription['prefixesPartIncrement'][1]['title']); // next possible value
        self::assertSame('part.edit.tab.advanced.ipn.prefix.description.increment', $suggestionsWithSameDescription['prefixesPartIncrement'][1]['description']);

        // Check expected values with next possible increment at category level
        self::assertSame('RES-0001', $suggestionsWithSameDescription['prefixesPartIncrement'][2]['title']); // next possible free increment for given part category
        self::assertSame('part.edit.tab.advanced.ipn.prefix.direct_category.increment', $suggestionsWithSameDescription['prefixesPartIncrement'][2]['description']);
        self::assertSame('PCOM-RES-0001', $suggestionsWithSameDescription['prefixesPartIncrement'][3]['title']); // next possible free increment for given part category
        self::assertSame('part.edit.tab.advanced.ipn.prefix.hierarchical.increment', $suggestionsWithSameDescription['prefixesPartIncrement'][3]['description']);
    }
}
