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
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\Part;
use App\Entity\Parts\StorageLocation;
use App\Entity\Parts\Supplier;
use App\Entity\UserSystem\User;
use App\Mcp\DTO\CreatePartInput;
use App\State\Mcp\CreatePartProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CreatePartProcessorTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private CreatePartProcessor $processor;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $userRepository = self::getContainer()->get('doctrine')->getRepository(User::class);
        $admin = $userRepository->findOneBy(['name' => 'admin']);
        $this->client->loginUser($admin);

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->processor = self::getContainer()->get(CreatePartProcessor::class);
    }

    private function getOperation(): Post
    {
        return new Post();
    }

    /**
     * Builds a CreatePartInput from a raw arguments array the same way CreatePartInputProvider does, so tests
     * exercise the real "which keys were actually provided" logic instead of constructing the DTO directly.
     */
    private function buildInput(array $data): CreatePartInput
    {
        return CreatePartInput::fromArray($data);
    }

    /**
     * Expected failures (permission denied, not found, bad input, validation) are reported as a successful
     * CallToolResult with isError:true and an explanatory text - not as a thrown exception - so the calling AI
     * agent actually sees why the call failed instead of an opaque protocol-level error. See McpToolErrorHandling.
     */
    private function assertErrorResult(mixed $result, string $messageContains): void
    {
        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertTrue($result->isError);
        self::assertInstanceOf(TextContent::class, $result->content[0]);
        self::assertStringContainsString($messageContains, $result->content[0]->text);
    }

    public function testCreatesMinimalPartWithOnlyNameAndCategory(): void
    {
        //Category is the one field that's mandatory on every part (Assert\NotNull), even though it's expressed
        //as a plain optional "categoryId" field on CreatePartInput - omitting it must fail validation, not silently succeed.
        $category = $this->em->getRepository(Category::class)->findOneBy(['name' => 'Node 1']);
        self::assertNotNull($category);

        $input = $this->buildInput(['name' => 'MCP Test Part Minimal', 'categoryId' => $category->getID()]);

        $result = $this->processor->process($input, $this->getOperation());

        self::assertInstanceOf(Part::class, $result);
        self::assertNotNull($result->getID());
        self::assertSame('MCP Test Part Minimal', $result->getName());
    }

    public function testCreatesPartLinkedToInfoProvider(): void
    {
        $category = $this->em->getRepository(Category::class)->findOneBy(['name' => 'Node 1']);
        self::assertNotNull($category);

        $input = $this->buildInput([
            'name' => 'MCP Test Part From Provider',
            'categoryId' => $category->getID(),
            'providerKey' => 'digikey',
            'providerId' => 'ABC123',
            'providerUrl' => 'https://example.com/abc123',
        ]);

        $result = $this->processor->process($input, $this->getOperation());

        self::assertTrue($result->getProviderReference()->isProviderCreated());
        self::assertSame('digikey', $result->getProviderReference()->getProviderKey());
        self::assertSame('ABC123', $result->getProviderReference()->getProviderId());
    }

    public function testOmittingRequiredCategoryFailsValidation(): void
    {
        $input = $this->buildInput(['name' => 'MCP Test Part Without Category']);

        $result = $this->processor->process($input, $this->getOperation());

        $this->assertErrorResult($result, 'category');
    }

    public function testCreatesPartWithNestedCollections(): void
    {
        $category = $this->em->getRepository(Category::class)->findOneBy(['name' => 'Node 1']);
        $footprint = $this->em->getRepository(Footprint::class)->findOneBy(['name' => 'Node 1']);
        $manufacturer = $this->em->getRepository(Manufacturer::class)->findOneBy(['name' => 'Node 1']);
        $storageLocation = $this->em->getRepository(StorageLocation::class)->findOneBy(['name' => 'Node 1']);
        $supplier = $this->em->getRepository(Supplier::class)->findOneBy(['name' => 'Node 1']);
        self::assertNotNull($category);
        self::assertNotNull($footprint);
        self::assertNotNull($manufacturer);
        self::assertNotNull($storageLocation);
        self::assertNotNull($supplier);

        $otherPart = $this->em->getRepository(Part::class)->findOneBy([]);
        self::assertNotNull($otherPart, 'Fixtures must contain at least one part');

        $input = $this->buildInput([
            'name' => 'MCP Test Part With Nested Data',
            'description' => 'A description',
            'comment' => 'A comment',
            'favorite' => true,
            'categoryId' => $category->getID(),
            'footprintId' => $footprint->getID(),
            'manufacturerId' => $manufacturer->getID(),
            'manufacturerProductNumber' => 'MPN-123',
            'manufacturingStatus' => 'active',
            'minAmount' => 5.0,
            'needsReview' => true,
            'tags' => 'tag1,tag2',
            'mass' => 1.5,
            'ipn' => 'IPN-MCP-1',
            'partLots' => [
                ['description' => 'Lot 1', 'storageLocationId' => $storageLocation->getID(), 'amount' => 10.0],
            ],
            'parameters' => [
                ['name' => 'Voltage', 'valueTypical' => 5.0, 'unit' => 'V'],
            ],
            'orderdetails' => [
                ['supplierId' => $supplier->getID(), 'supplierPartNr' => 'SUP-1', 'pricedetails' => [
                    ['price' => '1.23', 'minDiscountQuantity' => 1.0],
                ]],
            ],
            'associatedPartsAsOwner' => [
                ['otherPartId' => $otherPart->getID(), 'type' => 'COMPATIBLE'],
            ],
        ]);

        $result = $this->processor->process($input, $this->getOperation());

        self::assertSame('A description', $result->getDescription());
        self::assertSame($category, $result->getCategory());
        self::assertCount(1, $result->getPartLots());
        self::assertSame(10.0, $result->getPartLots()->first()->getAmount());
        self::assertCount(1, $result->getParameters());
        self::assertCount(1, $result->getOrderdetails());
        self::assertCount(1, $result->getOrderdetails()->first()->getPricedetails());
        self::assertCount(1, $result->getAssociatedPartsAsOwner());
        self::assertSame($otherPart, $result->getAssociatedPartsAsOwner()->first()->getOther());
    }

    public function testRejectsExistingLotIdOnCreate(): void
    {
        $input = $this->buildInput([
            'name' => 'MCP Test Part Invalid Lot',
            'partLots' => [['id' => 999, 'amount' => 1.0]],
        ]);

        $result = $this->processor->process($input, $this->getOperation());

        $this->assertErrorResult($result, 'does not belong to this part');
    }

    public function testValidationFailureForCategoryNameRegex(): void
    {
        $category = new Category();
        $category->setName('Regex-constrained category');
        $category->setPartnameRegex('/^REQUIRED-PREFIX-/');
        $this->em->persist($category);
        $this->em->flush();

        $input = $this->buildInput(['name' => 'Non-matching name', 'categoryId' => $category->getID()]);

        $result = $this->processor->process($input, $this->getOperation());

        $this->assertErrorResult($result, 'name');
    }

    public function testDeniesAccessForUserWithoutCreatePermission(): void
    {
        $noreadUser = self::getContainer()->get('doctrine')->getRepository(User::class)->findOneBy(['name' => 'noread']);
        self::assertNotNull($noreadUser);
        $this->client->loginUser($noreadUser);

        $input = $this->buildInput(['name' => 'Should not be created']);

        $result = $this->processor->process($input, $this->getOperation());

        $this->assertErrorResult($result, 'not allowed to create parts');
    }
}
