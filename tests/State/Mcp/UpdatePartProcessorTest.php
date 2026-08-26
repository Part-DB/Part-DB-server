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

use ApiPlatform\Metadata\Patch;
use App\Entity\Parts\Category;
use App\Entity\Parts\InfoProviderReference;
use App\Entity\Parts\ManufacturingStatus;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\StorageLocation;
use App\Entity\UserSystem\User;
use App\Mcp\DTO\UpdatePartInput;
use App\State\Mcp\UpdatePartProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UpdatePartProcessorTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private UpdatePartProcessor $processor;
    private Part $part;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $userRepository = self::getContainer()->get('doctrine')->getRepository(User::class);
        $admin = $userRepository->findOneBy(['name' => 'admin']);
        $this->client->loginUser($admin);

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->processor = self::getContainer()->get(UpdatePartProcessor::class);

        $category = $this->em->getRepository(Category::class)->findOneBy(['name' => 'Node 1']);
        self::assertNotNull($category);

        $this->part = new Part();
        $this->part->setName('MCP Update Test Part');
        $this->part->setDescription('Original description');
        $this->part->setCategory($category);
        $this->em->persist($this->part);
        $this->em->flush();
    }

    private function getOperation(): Patch
    {
        return new Patch();
    }

    /**
     * Builds an UpdatePartInput from a raw arguments array the same way UpdatePartInputProvider does,
     * so tests exercise the real "only provided keys are applied" semantics.
     */
    private function buildInput(array $data): UpdatePartInput
    {
        return UpdatePartInput::fromArray($data);
    }

    /**
     * Expected failures (permission denied, not found, bad input) are reported as a successful CallToolResult
     * with isError:true and an explanatory text - not as a thrown exception. See McpToolErrorHandling.
     */
    private function assertErrorResult(mixed $result, string $messageContains): void
    {
        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertTrue($result->isError);
        self::assertInstanceOf(TextContent::class, $result->content[0]);
        self::assertStringContainsString($messageContains, $result->content[0]->text);
    }

    public function testUpdatingOnlyNameLeavesOtherFieldsUntouched(): void
    {
        $input = $this->buildInput(['id' => $this->part->getID(), 'name' => 'Renamed Part']);

        $result = $this->processor->process($input, $this->getOperation());

        self::assertSame('Renamed Part', $result->getName());
        self::assertSame('Original description', $result->getDescription());
    }

    public function testReconcilesPartLotsByIdCreateUpdateRemove(): void
    {
        $storageLocation = $this->em->getRepository(StorageLocation::class)->findOneBy(['name' => 'Node 1']);
        $storageLocation2 = $this->em->getRepository(StorageLocation::class)->findOneBy(['name' => 'Node 2']);
        self::assertNotNull($storageLocation);
        self::assertNotNull($storageLocation2);

        $keepLot = new PartLot();
        $keepLot->setDescription('Keep me (updated)');
        $keepLot->setAmount(1.0);
        $this->part->addPartLot($keepLot);

        $removeLot = new PartLot();
        $removeLot->setDescription('Remove me');
        $removeLot->setAmount(2.0);
        $this->part->addPartLot($removeLot);

        $this->em->flush();
        $keepLotId = $keepLot->getID();
        $removeLotId = $removeLot->getID();
        self::assertNotNull($keepLotId);
        self::assertNotNull($removeLotId);

        //Omit the "removeLot" entirely -> it must be removed. Update "keepLot" by id. Add a brand-new lot (no id).
        $input = $this->buildInput([
            'id' => $this->part->getID(),
            'partLots' => [
                ['id' => $keepLotId, 'storageLocationId' => $storageLocation->getID()],
                ['description' => 'New lot', 'storageLocationId' => $storageLocation2->getID(), 'amount' => 5.0],
            ],
        ]);

        $result = $this->processor->process($input, $this->getOperation());

        self::assertCount(2, $result->getPartLots());
        $descriptions = array_map(static fn (PartLot $lot) => $lot->getDescription(), $result->getPartLots()->toArray());
        self::assertContains('Keep me (updated)', $descriptions);
        self::assertContains('New lot', $descriptions);
        self::assertNotContains('Remove me', $descriptions);
    }

    public function testRejectsSettingAmountOnExistingLot(): void
    {
        $lot = new PartLot();
        $lot->setDescription('Existing lot');
        $lot->setAmount(1.0);
        $this->part->addPartLot($lot);
        $this->em->flush();

        $input = $this->buildInput([
            'id' => $this->part->getID(),
            'partLots' => [
                ['id' => $lot->getID(), 'amount' => 999.0],
            ],
        ]);

        $result = $this->processor->process($input, $this->getOperation());

        $this->assertErrorResult($result, 'withdraw_part_stock');
    }

    public function testExplicitNullManufacturingStatusResetsToNotSet(): void
    {
        $this->part->setManufacturingStatus(ManufacturingStatus::ACTIVE);
        $this->em->flush();

        //Explicitly providing null must actually clear the field, not silently no-op like an omitted key would
        $input = $this->buildInput(['id' => $this->part->getID(), 'manufacturingStatus' => null]);

        $result = $this->processor->process($input, $this->getOperation());

        self::assertSame(ManufacturingStatus::NOT_SET, $result->getManufacturingStatus());
    }

    public function testLinksPartToInfoProvider(): void
    {
        $input = $this->buildInput([
            'id' => $this->part->getID(),
            'providerKey' => 'digikey',
            'providerId' => 'ABC123',
            'providerUrl' => 'https://example.com/abc123',
        ]);

        $result = $this->processor->process($input, $this->getOperation());

        self::assertTrue($result->getProviderReference()->isProviderCreated());
        self::assertSame('digikey', $result->getProviderReference()->getProviderKey());
        self::assertSame('ABC123', $result->getProviderReference()->getProviderId());
        self::assertSame('https://example.com/abc123', $result->getProviderReference()->getProviderUrl());
        self::assertNotNull($result->getProviderReference()->getLastUpdated());
    }

    public function testUnlinksPartFromInfoProvider(): void
    {
        $this->part->setProviderReference(InfoProviderReference::providerReference('digikey', 'ABC123'));
        $this->em->flush();

        $input = $this->buildInput(['id' => $this->part->getID(), 'providerKey' => null, 'providerId' => null]);

        $result = $this->processor->process($input, $this->getOperation());

        self::assertFalse($result->getProviderReference()->isProviderCreated());
        self::assertNull($result->getProviderReference()->getProviderKey());
        self::assertNull($result->getProviderReference()->getProviderId());
    }

    public function testMismatchedProviderKeyAndIdFailsValidation(): void
    {
        //providerId without providerKey is an invalid combination (InfoProviderReference::validate())
        $input = $this->buildInput(['id' => $this->part->getID(), 'providerId' => 'ABC123']);

        $result = $this->processor->process($input, $this->getOperation());

        $this->assertErrorResult($result, 'provider_key');
    }

    public function testOmittingProviderFieldsLeavesReferenceUntouched(): void
    {
        $this->part->setProviderReference(InfoProviderReference::providerReference('digikey', 'ABC123'));
        $this->em->flush();

        $input = $this->buildInput(['id' => $this->part->getID(), 'name' => 'Renamed but still linked']);

        $result = $this->processor->process($input, $this->getOperation());

        self::assertTrue($result->getProviderReference()->isProviderCreated());
        self::assertSame('digikey', $result->getProviderReference()->getProviderKey());
    }

    public function testThrowsNotFoundForUnknownPartId(): void
    {
        $input = $this->buildInput(['id' => 999999999, 'name' => 'Does not matter']);
        $result = $this->processor->process($input, $this->getOperation());

        $this->assertErrorResult($result, 'not found');
    }

    public function testDeniesAccessForUserWithoutEditPermission(): void
    {
        $noreadUser = self::getContainer()->get('doctrine')->getRepository(User::class)->findOneBy(['name' => 'noread']);
        self::assertNotNull($noreadUser);
        $this->client->loginUser($noreadUser);

        $input = $this->buildInput(['id' => $this->part->getID(), 'name' => 'Should not change']);
        $result = $this->processor->process($input, $this->getOperation());

        $this->assertErrorResult($result, 'Access denied');
    }
}
