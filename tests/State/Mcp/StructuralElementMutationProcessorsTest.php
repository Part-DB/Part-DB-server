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
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\Part;
use App\Entity\Parts\StorageLocation;
use App\Entity\Parts\Supplier;
use App\Entity\UserSystem\User;
use App\Mcp\DTO\CreateStructuralElementInput;
use App\Mcp\DTO\DeleteStructuralElementInput;
use App\Mcp\DTO\UpdateStructuralElementInput;
use App\Settings\AISettings\McpSettings;
use App\State\Mcp\CreateStructuralElementProcessor;
use App\State\Mcp\DeleteStructuralElementProcessor;
use App\State\Mcp\UpdateStructuralElementProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class StructuralElementMutationProcessorsTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private McpSettings $mcpSettings;
    private CreateStructuralElementProcessor $createProcessor;
    private UpdateStructuralElementProcessor $updateProcessor;
    private DeleteStructuralElementProcessor $deleteProcessor;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $userRepository = self::getContainer()->get('doctrine')->getRepository(User::class);
        $admin = $userRepository->findOneBy(['name' => 'admin']);
        $this->client->loginUser($admin);

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->createProcessor = self::getContainer()->get(CreateStructuralElementProcessor::class);
        $this->updateProcessor = self::getContainer()->get(UpdateStructuralElementProcessor::class);
        $this->deleteProcessor = self::getContainer()->get(DeleteStructuralElementProcessor::class);

        //Editing MCP tools is disabled by default (McpSettings::$editingEnabled) - enable it for these tests,
        //which exercise the tools' actual behavior; testDeniesAccessWhenEditingToolsDisabled() covers the guard itself.
        $this->mcpSettings = self::getContainer()->get(McpSettings::class);
        $this->mcpSettings->editingEnabled = true;
    }

    /**
     * @return iterable<string, array{0: class-string<AbstractStructuralDBElement>}>
     */
    public static function provideClasses(): iterable
    {
        yield 'category' => [Category::class];
        yield 'footprint' => [Footprint::class];
        yield 'manufacturer' => [Manufacturer::class];
        yield 'storage_location' => [StorageLocation::class];
        yield 'supplier' => [Supplier::class];
    }

    private function assertErrorResult(mixed $result, string $messageContains): void
    {
        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertTrue($result->isError);
        self::assertInstanceOf(TextContent::class, $result->content[0]);
        self::assertStringContainsString($messageContains, $result->content[0]->text);
    }

    #[DataProvider('provideClasses')]
    public function testCreatesMinimalElementWithOnlyName(string $class): void
    {
        $input = CreateStructuralElementInput::fromArray(['name' => 'MCP Test Element Minimal']);

        $result = $this->createProcessor->process($input, (new Post())->withClass($class));

        self::assertInstanceOf($class, $result);
        self::assertNotNull($result->getID());
        self::assertSame('MCP Test Element Minimal', $result->getName());
    }

    #[DataProvider('provideClasses')]
    public function testCreatesElementWithAllFields(string $class): void
    {
        $parent = new $class();
        $parent->setName('MCP Test Parent');
        $this->em->persist($parent);
        $this->em->flush();

        $input = CreateStructuralElementInput::fromArray([
            'name' => 'MCP Test Element Full',
            'comment' => 'A comment',
            'notSelectable' => true,
            'parentId' => $parent->getID(),
            'alternativeNames' => 'Alt1,Alt2',
        ]);

        $result = $this->createProcessor->process($input, (new Post())->withClass($class));

        self::assertInstanceOf($class, $result);
        self::assertSame('A comment', $result->getComment());
        self::assertTrue($result->isNotSelectable());
        self::assertSame($parent, $result->getParent());
        self::assertSame('Alt1,Alt2', $result->getAlternativeNames());
    }

    #[DataProvider('provideClasses')]
    public function testUpdatingOnlyNameLeavesOtherFieldsUntouched(string $class): void
    {
        $element = new $class();
        $element->setName('MCP Update Test Element');
        $element->setComment('Original comment');
        $this->em->persist($element);
        $this->em->flush();

        $input = UpdateStructuralElementInput::fromArray(['id' => $element->getID(), 'name' => 'Renamed Element']);

        $result = $this->updateProcessor->process($input, (new Patch())->withClass($class));

        self::assertSame('Renamed Element', $result->getName());
        self::assertSame('Original comment', $result->getComment());
    }

    #[DataProvider('provideClasses')]
    public function testUpdateCanClearParentAndAlternativeNames(string $class): void
    {
        $parent = new $class();
        $parent->setName('MCP Test Parent For Clear');
        $this->em->persist($parent);

        $element = new $class();
        $element->setName('MCP Test Child');
        $element->setParent($parent);
        $element->setAlternativeNames('Foo,Bar');
        $this->em->persist($element);
        $this->em->flush();

        $input = UpdateStructuralElementInput::fromArray([
            'id' => $element->getID(),
            'parentId' => null,
            'alternativeNames' => null,
        ]);

        $result = $this->updateProcessor->process($input, (new Patch())->withClass($class));

        self::assertNull($result->getParent());
        self::assertNull($result->getAlternativeNames());
    }

    #[DataProvider('provideClasses')]
    public function testUpdateRejectsCircularParent(string $class): void
    {
        $element = new $class();
        $element->setName('MCP Test Self Parent');
        $this->em->persist($element);
        $this->em->flush();

        $input = UpdateStructuralElementInput::fromArray(['id' => $element->getID(), 'parentId' => $element->getID()]);

        $result = $this->updateProcessor->process($input, (new Patch())->withClass($class));

        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertTrue($result->isError);
    }

    #[DataProvider('provideClasses')]
    public function testDeletesElementAndReturnsConfirmation(string $class): void
    {
        $element = new $class();
        $element->setName('MCP Delete Test Element');
        $this->em->persist($element);
        $this->em->flush();
        $id = $element->getID();

        $result = $this->deleteProcessor->process(new DeleteStructuralElementInput(id: $id), (new Delete())->withClass($class));

        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertFalse($result->isError);
        self::assertInstanceOf(TextContent::class, $result->content[0]);
        self::assertStringContainsString('MCP Delete Test Element', $result->content[0]->text);

        self::assertNull($this->em->getRepository($class)->find($id));
    }

    #[DataProvider('provideClasses')]
    public function testDeleteReparentsChildrenInsteadOfDeletingThem(string $class): void
    {
        $grandparent = new $class();
        $grandparent->setName('MCP Grandparent');
        $this->em->persist($grandparent);

        $middle = new $class();
        $middle->setName('MCP Middle');
        $middle->setParent($grandparent);
        $this->em->persist($middle);

        $child = new $class();
        $child->setName('MCP Child');
        $child->setParent($middle);
        $this->em->persist($child);
        $this->em->flush();

        $result = $this->deleteProcessor->process(new DeleteStructuralElementInput(id: $middle->getID()), (new Delete())->withClass($class));

        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertFalse($result->isError);
        self::assertStringContainsString('1 child element', $result->content[0]->text);

        $this->em->refresh($child);
        self::assertSame($grandparent, $child->getParent());
    }

    #[DataProvider('provideClasses')]
    public function testThrowsNotFoundForUnknownIdOnUpdateAndDelete(string $class): void
    {
        $updateResult = $this->updateProcessor->process(
            UpdateStructuralElementInput::fromArray(['id' => 999999999, 'name' => 'Does not matter']),
            (new Patch())->withClass($class)
        );
        $this->assertErrorResult($updateResult, 'not found');

        $deleteResult = $this->deleteProcessor->process(new DeleteStructuralElementInput(id: 999999999), (new Delete())->withClass($class));
        $this->assertErrorResult($deleteResult, 'not found');
    }

    #[DataProvider('provideClasses')]
    public function testDeniesAccessForUserWithoutPermission(string $class): void
    {
        $element = new $class();
        $element->setName('MCP Permission Test Element');
        $this->em->persist($element);
        $this->em->flush();

        $noreadUser = self::getContainer()->get('doctrine')->getRepository(User::class)->findOneBy(['name' => 'noread']);
        self::assertNotNull($noreadUser);
        $this->client->loginUser($noreadUser);

        $createResult = $this->createProcessor->process(
            CreateStructuralElementInput::fromArray(['name' => 'Should not be created']),
            (new Post())->withClass($class)
        );
        $this->assertErrorResult($createResult, 'not allowed');

        $updateResult = $this->updateProcessor->process(
            UpdateStructuralElementInput::fromArray(['id' => $element->getID(), 'name' => 'Should not change']),
            (new Patch())->withClass($class)
        );
        $this->assertErrorResult($updateResult, 'Access denied');

        $deleteResult = $this->deleteProcessor->process(new DeleteStructuralElementInput(id: $element->getID()), (new Delete())->withClass($class));
        $this->assertErrorResult($deleteResult, 'Access denied');
    }

    #[DataProvider('provideClasses')]
    public function testDeniesAccessWhenEditingToolsDisabled(string $class): void
    {
        $this->mcpSettings->editingEnabled = false;

        $result = $this->createProcessor->process(
            CreateStructuralElementInput::fromArray(['name' => 'Should not be created']),
            (new Post())->withClass($class)
        );

        $this->assertErrorResult($result, 'Editing via MCP tools is disabled');
    }

    public function testDeleteFailsWhenCategoryStillContainsParts(): void
    {
        $category = new Category();
        $category->setName('MCP Category With Parts');
        $this->em->persist($category);

        $part = new Part();
        $part->setName('MCP Test Part In Category');
        $part->setCategory($category);
        $this->em->persist($part);
        $this->em->flush();

        $result = $this->deleteProcessor->process(new DeleteStructuralElementInput(id: $category->getID()), (new Delete())->withClass(Category::class));

        $this->assertErrorResult($result, 'still contains');
        self::assertNotNull($this->em->getRepository(Category::class)->find($category->getID()));
    }
}
