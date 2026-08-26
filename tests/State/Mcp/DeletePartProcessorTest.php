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

use ApiPlatform\Metadata\Delete;
use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use App\Entity\UserSystem\User;
use App\Mcp\DTO\DeletePartInput;
use App\State\Mcp\DeletePartProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DeletePartProcessorTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private DeletePartProcessor $processor;
    private Part $part;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $userRepository = self::getContainer()->get('doctrine')->getRepository(User::class);
        $admin = $userRepository->findOneBy(['name' => 'admin']);
        $this->client->loginUser($admin);

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->processor = self::getContainer()->get(DeletePartProcessor::class);

        $category = $this->em->getRepository(Category::class)->findOneBy(['name' => 'Node 1']);
        self::assertNotNull($category);

        $this->part = new Part();
        $this->part->setName('MCP Delete Test Part');
        $this->part->setCategory($category);
        $this->em->persist($this->part);
        $this->em->flush();
    }

    private function getOperation(): Delete
    {
        return new Delete();
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

    public function testDeletesPartAndReturnsConfirmation(): void
    {
        $id = $this->part->getID();
        self::assertNotNull($id);

        $result = $this->processor->process(new DeletePartInput(id: $id), $this->getOperation());

        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertFalse($result->isError);
        self::assertInstanceOf(TextContent::class, $result->content[0]);
        self::assertStringContainsString('MCP Delete Test Part', $result->content[0]->text);
        self::assertStringContainsString((string) $id, $result->content[0]->text);

        self::assertNull($this->em->getRepository(Part::class)->find($id));
    }

    public function testThrowsNotFoundForUnknownId(): void
    {
        $result = $this->processor->process(new DeletePartInput(id: 999999999), $this->getOperation());

        $this->assertErrorResult($result, 'not found');
    }

    public function testDeniesAccessForUserWithoutDeletePermission(): void
    {
        $noreadUser = self::getContainer()->get('doctrine')->getRepository(User::class)->findOneBy(['name' => 'noread']);
        self::assertNotNull($noreadUser);
        $this->client->loginUser($noreadUser);

        $id = $this->part->getID();
        self::assertNotNull($id);

        $result = $this->processor->process(new DeletePartInput(id: $id), $this->getOperation());

        $this->assertErrorResult($result, 'Access denied');

        //The part must still exist after a denied delete attempt
        self::assertNotNull($this->em->getRepository(Part::class)->find($id));
    }
}
