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

use ApiPlatform\Metadata\Post;
use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\UserSystem\User;
use App\Mcp\DTO\AddPartStockInput;
use App\Mcp\DTO\StocktakePartLotInput;
use App\Mcp\DTO\WithdrawPartStockInput;
use App\Settings\AISettings\McpSettings;
use App\State\Mcp\AddPartStockProcessor;
use App\State\Mcp\StocktakePartLotProcessor;
use App\State\Mcp\WithdrawPartStockProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Covers the wiring (id resolution, 404, permission check, correct return value) of the stock-adjustment MCP
 * tools (withdraw/add/stocktake). The underlying arithmetic (rounding, insufficient-stock checks, ...) is
 * already covered by PartLotWithdrawAddHelperTest, so it is not re-tested here.
 */
class PartLotStockProcessorsTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private McpSettings $mcpSettings;
    private Part $part;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $userRepository = self::getContainer()->get('doctrine')->getRepository(User::class);
        $admin = $userRepository->findOneBy(['name' => 'admin']);
        $this->client->loginUser($admin);

        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        //Editing MCP tools is disabled by default (McpSettings::$editingEnabled) - enable it for these tests,
        //which exercise the tools' actual behavior; testDeniesAccessWhenEditingToolsDisabled() covers the guard itself.
        $this->mcpSettings = self::getContainer()->get(McpSettings::class);
        $this->mcpSettings->editingEnabled = true;

        $category = $this->em->getRepository(Category::class)->findOneBy(['name' => 'Node 1']);
        self::assertNotNull($category);

        $this->part = new Part();
        $this->part->setName('MCP Stock Test Part');
        $this->part->setCategory($category);
        $this->em->persist($this->part);
        $this->em->flush();
    }

    private function createLot(float $amount, ?User $owner = null): PartLot
    {
        $lot = new PartLot();
        $lot->setAmount($amount);
        $lot->setOwner($owner);
        $this->part->addPartLot($lot);
        $this->em->persist($lot);
        $this->em->flush();

        return $lot;
    }

    public function testWithdrawReducesAmount(): void
    {
        $lot = $this->createLot(10.0);

        /** @var WithdrawPartStockProcessor $processor */
        $processor = self::getContainer()->get(WithdrawPartStockProcessor::class);
        $result = $processor->process(new WithdrawPartStockInput(lotId: $lot->getID(), amount: 4.0), new Post());

        self::assertInstanceOf(Part::class, $result);
        self::assertSame(6.0, $lot->getAmount());
    }

    public function testAddIncreasesAmount(): void
    {
        $lot = $this->createLot(10.0);

        /** @var AddPartStockProcessor $processor */
        $processor = self::getContainer()->get(AddPartStockProcessor::class);
        $processor->process(new AddPartStockInput(lotId: $lot->getID(), amount: 5.0), new Post());

        self::assertSame(15.0, $lot->getAmount());
    }

    public function testStocktakeSetsAbsoluteAmount(): void
    {
        $lot = $this->createLot(10.0);

        /** @var StocktakePartLotProcessor $processor */
        $processor = self::getContainer()->get(StocktakePartLotProcessor::class);
        $processor->process(new StocktakePartLotInput(lotId: $lot->getID(), actualAmount: 42.0), new Post());

        self::assertSame(42.0, $lot->getAmount());
        self::assertNotNull($lot->getLastStocktakeAt());
    }

    /**
     * Expected failures (permission denied, not found) are reported as a successful CallToolResult with
     * isError:true and an explanatory text - not as a thrown exception. See McpToolErrorHandling.
     */
    private function assertErrorResult(mixed $result, string $messageContains): void
    {
        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertTrue($result->isError);
        self::assertInstanceOf(TextContent::class, $result->content[0]);
        self::assertStringContainsString($messageContains, $result->content[0]->text);
    }

    public function testThrowsNotFoundForUnknownLotId(): void
    {
        /** @var WithdrawPartStockProcessor $processor */
        $processor = self::getContainer()->get(WithdrawPartStockProcessor::class);

        $result = $processor->process(new WithdrawPartStockInput(lotId: 999999999, amount: 1.0), new Post());

        $this->assertErrorResult($result, 'not found');
    }

    public function testDeniesWithdrawForUserWhoIsNotTheLotOwner(): void
    {
        $admin = self::getContainer()->get('doctrine')->getRepository(User::class)->findOneBy(['name' => 'admin']);
        self::assertNotNull($admin);
        //Lot is owned by "admin"
        $lot = $this->createLot(10.0, $admin);

        //"user" has the general parts_stock.withdraw permission (editor preset) but is not this lot's owner
        $otherUser = self::getContainer()->get('doctrine')->getRepository(User::class)->findOneBy(['name' => 'user']);
        self::assertNotNull($otherUser);
        $this->client->loginUser($otherUser);

        /** @var WithdrawPartStockProcessor $processor */
        $processor = self::getContainer()->get(WithdrawPartStockProcessor::class);

        $result = $processor->process(new WithdrawPartStockInput(lotId: $lot->getID(), amount: 1.0), new Post());

        $this->assertErrorResult($result, 'Access denied');
    }

    public function testDeniesAccessForUserWithoutStockPermission(): void
    {
        $lot = $this->createLot(10.0);

        $noreadUser = self::getContainer()->get('doctrine')->getRepository(User::class)->findOneBy(['name' => 'noread']);
        self::assertNotNull($noreadUser);
        $this->client->loginUser($noreadUser);

        /** @var WithdrawPartStockProcessor $processor */
        $processor = self::getContainer()->get(WithdrawPartStockProcessor::class);

        $result = $processor->process(new WithdrawPartStockInput(lotId: $lot->getID(), amount: 1.0), new Post());

        $this->assertErrorResult($result, 'Access denied');
    }

    public function testDeniesAccessWhenEditingToolsDisabled(): void
    {
        $this->mcpSettings->editingEnabled = false;
        $lot = $this->createLot(10.0);

        /** @var WithdrawPartStockProcessor $processor */
        $processor = self::getContainer()->get(WithdrawPartStockProcessor::class);

        $result = $processor->process(new WithdrawPartStockInput(lotId: $lot->getID(), amount: 1.0), new Post());

        $this->assertErrorResult($result, 'Editing via MCP tools is disabled');
    }
}
