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

namespace App\Tests\State\Mcp;

use ApiPlatform\Metadata\GetCollection;
use App\Entity\Parts\Category;
use App\Entity\Parts\ManufacturingStatus;
use App\Entity\Parts\Part;
use App\Entity\UserSystem\User;
use App\Mcp\DTO\AdvancedPartSearchInput;
use App\State\Mcp\AdvancedSearchPartsProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdvancedSearchPartsProcessorTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private AdvancedSearchPartsProcessor $processor;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $userRepository = self::getContainer()->get('doctrine')->getRepository(User::class);
        $admin = $userRepository->findOneBy(['name' => 'admin']);
        $this->client->loginUser($admin);

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->processor = self::getContainer()->get(AdvancedSearchPartsProcessor::class);
    }

    private function search(array $data): mixed
    {
        return $this->processor->process(AdvancedPartSearchInput::fromArray($data), new GetCollection());
    }

    private function makePart(string $name, ?callable $configure = null): Part
    {
        $category = $this->em->getRepository(Category::class)->findOneBy(['name' => 'Node 1']);
        self::assertNotNull($category, 'Fixtures must contain a category named "Node 1"');

        $part = new Part();
        $part->setName($name);
        $part->setCategory($category);

        if ($configure !== null) {
            $configure($part);
        }

        $this->em->persist($part);
        $this->em->flush();

        return $part;
    }

    public function testTextFilterContains(): void
    {
        $part = $this->makePart('MCP-Advanced-Search-Unique-Resistor');

        $result = $this->search([
            'name' => ['operator' => 'CONTAINS', 'value' => 'Advanced-Search-Unique-Resistor'],
        ]);

        self::assertIsArray($result);
        self::assertCount(1, $result);
        self::assertSame($part->getID(), $result[0]->getID());
    }

    public function testTextFilterFindsNothingForUnmatchedKeyword(): void
    {
        $this->makePart('MCP-Advanced-Search-Capacitor');

        $result = $this->search([
            'name' => ['operator' => 'CONTAINS', 'value' => 'ThisKeywordMatchesNoPart12345'],
        ]);

        self::assertIsArray($result);
        self::assertCount(0, $result);
    }

    public function testEntityFilterByCategoryId(): void
    {
        $category = $this->em->getRepository(Category::class)->findOneBy(['name' => 'Node 1']);
        self::assertNotNull($category);

        $part = $this->makePart('MCP-Advanced-Search-ByCategory');

        $result = $this->search([
            'name' => ['operator' => 'STARTS', 'value' => 'MCP-Advanced-Search-ByCategory'],
            'category' => ['operator' => '=', 'id' => $category->getID()],
        ]);

        self::assertCount(1, $result);
        self::assertSame($part->getID(), $result[0]->getID());
    }

    public function testUnknownCategoryIdReturnsErrorResult(): void
    {
        $result = $this->search([
            'category' => ['operator' => '=', 'id' => 999999],
        ]);

        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertTrue($result->isError);
        self::assertInstanceOf(TextContent::class, $result->content[0]);
        self::assertStringContainsString('not found', $result->content[0]->text);
    }

    public function testNumberFilterBetween(): void
    {
        $part = $this->makePart('MCP-Advanced-Search-Amount', function (Part $part): void {
            $part->setMinAmount(42.0);
        });

        $result = $this->search([
            'name' => ['operator' => 'STARTS', 'value' => 'MCP-Advanced-Search-Amount'],
            'minAmount' => ['operator' => 'BETWEEN', 'value' => 40, 'value2' => 50],
        ]);

        self::assertCount(1, $result);
        self::assertSame($part->getID(), $result[0]->getID());

        $resultOutsideRange = $this->search([
            'name' => ['operator' => 'STARTS', 'value' => 'MCP-Advanced-Search-Amount'],
            'minAmount' => ['operator' => 'BETWEEN', 'value' => 100, 'value2' => 200],
        ]);
        self::assertCount(0, $resultOutsideRange);
    }

    public function testBooleanFilterFavorite(): void
    {
        $favoritePart = $this->makePart('MCP-Advanced-Search-Favorite-Yes', function (Part $part): void {
            $part->setFavorite(true);
        });
        $this->makePart('MCP-Advanced-Search-Favorite-No', function (Part $part): void {
            $part->setFavorite(false);
        });

        $result = $this->search([
            'name' => ['operator' => 'STARTS', 'value' => 'MCP-Advanced-Search-Favorite'],
            'favorite' => true,
        ]);

        self::assertCount(1, $result);
        self::assertSame($favoritePart->getID(), $result[0]->getID());
    }

    public function testTagsFilterAny(): void
    {
        $part = $this->makePart('MCP-Advanced-Search-Tags', function (Part $part): void {
            $part->setTags('mcp-test-tag-a,mcp-test-tag-b');
        });
        $this->makePart('MCP-Advanced-Search-Tags-Other', function (Part $part): void {
            $part->setTags('mcp-test-tag-c');
        });

        $result = $this->search([
            'name' => ['operator' => 'STARTS', 'value' => 'MCP-Advanced-Search-Tags'],
            'tags' => ['operator' => 'ANY', 'tags' => ['mcp-test-tag-a']],
        ]);

        self::assertCount(1, $result);
        self::assertSame($part->getID(), $result[0]->getID());
    }

    public function testManufacturingStatusChoiceFilter(): void
    {
        $part = $this->makePart('MCP-Advanced-Search-Status-Active', function (Part $part): void {
            $part->setManufacturingStatus(ManufacturingStatus::ACTIVE);
        });
        $this->makePart('MCP-Advanced-Search-Status-Eol', function (Part $part): void {
            $part->setManufacturingStatus(ManufacturingStatus::EOL);
        });

        $result = $this->search([
            'name' => ['operator' => 'STARTS', 'value' => 'MCP-Advanced-Search-Status'],
            'manufacturingStatus' => ['operator' => 'ANY', 'value' => ['active']],
        ]);

        self::assertCount(1, $result);
        self::assertSame($part->getID(), $result[0]->getID());
    }

    public function testCombinedFiltersAreCombinedWithAnd(): void
    {
        $matching = $this->makePart('MCP-Advanced-Search-Combined-Match', function (Part $part): void {
            $part->setFavorite(true);
            $part->setMinAmount(10.0);
        });
        $this->makePart('MCP-Advanced-Search-Combined-WrongAmount', function (Part $part): void {
            $part->setFavorite(true);
            $part->setMinAmount(999.0);
        });
        $this->makePart('MCP-Advanced-Search-Combined-NotFavorite', function (Part $part): void {
            $part->setFavorite(false);
            $part->setMinAmount(10.0);
        });

        $result = $this->search([
            'name' => ['operator' => 'STARTS', 'value' => 'MCP-Advanced-Search-Combined'],
            'favorite' => true,
            'minAmount' => ['operator' => '=', 'value' => 10.0],
        ]);

        self::assertCount(1, $result);
        self::assertSame($matching->getID(), $result[0]->getID());
    }

    public function testLimitIsApplied(): void
    {
        for ($i = 0; $i < 3; ++$i) {
            $this->makePart('MCP-Advanced-Search-Limit-' . $i);
        }

        $result = $this->search([
            'name' => ['operator' => 'STARTS', 'value' => 'MCP-Advanced-Search-Limit-'],
            'limit' => 2,
        ]);

        self::assertCount(2, $result);
    }

    public function testOrderByNameDescending(): void
    {
        $this->makePart('MCP-Advanced-Search-Order-A');
        $this->makePart('MCP-Advanced-Search-Order-B');
        $this->makePart('MCP-Advanced-Search-Order-C');

        $result = $this->search([
            'name' => ['operator' => 'STARTS', 'value' => 'MCP-Advanced-Search-Order-'],
            'orderBy' => 'name',
            'orderDirection' => 'DESC',
        ]);

        self::assertCount(3, $result);
        self::assertSame('MCP-Advanced-Search-Order-C', $result[0]->getName());
        self::assertSame('MCP-Advanced-Search-Order-A', $result[2]->getName());
    }

    public function testInvalidTextOperatorReturnsErrorResult(): void
    {
        $result = $this->search([
            'name' => ['operator' => 'NOT_A_REAL_OPERATOR', 'value' => 'foo'],
        ]);

        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertTrue($result->isError);
        self::assertInstanceOf(TextContent::class, $result->content[0]);
        self::assertStringContainsString('Invalid operator', $result->content[0]->text);
    }

    public function testInvalidOrderByReturnsErrorResult(): void
    {
        $result = $this->search(['orderBy' => 'not_a_real_field']);

        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertTrue($result->isError);
        self::assertStringContainsString('orderBy', $result->content[0]->text);
    }

    public function testParameterFilterMatchesPartWithMatchingParameter(): void
    {
        $part = $this->makePart('MCP-Advanced-Search-Parameter-Match', function (Part $part): void {
            $parameter = new \App\Entity\Parameters\PartParameter();
            $parameter->setName('Resistance');
            $parameter->setValueTypical(1000.0);
            $part->addParameter($parameter);
        });
        $this->makePart('MCP-Advanced-Search-Parameter-NoMatch', function (Part $part): void {
            $parameter = new \App\Entity\Parameters\PartParameter();
            $parameter->setName('Resistance');
            $parameter->setValueTypical(1.0);
            $part->addParameter($parameter);
        });

        $result = $this->search([
            'name' => ['operator' => 'STARTS', 'value' => 'MCP-Advanced-Search-Parameter'],
            'parameters' => [
                ['name' => 'Resistance', 'operator' => '>=', 'value' => 500],
            ],
        ]);

        self::assertCount(1, $result);
        self::assertSame($part->getID(), $result[0]->getID());
    }
}
