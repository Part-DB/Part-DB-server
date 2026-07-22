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

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartCustomState;
use App\Entity\Parts\StorageLocation;
use App\Entity\Parts\Supplier;
use App\Entity\UserSystem\User;
use App\Mcp\DTO\ElementByIdInput;
use App\Mcp\DTO\StructuralElementOverview;
use App\Mcp\DTO\StructuralElementSearchInput;
use App\State\Mcp\GetStructuralElementDetailsProcessor;
use App\State\Mcp\ListStructuralElementsProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Serializer\SerializerInterface;

class StructuralElementProcessorsTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private ListStructuralElementsProcessor $listProcessor;
    private GetStructuralElementDetailsProcessor $getProcessor;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $userRepository = self::getContainer()->get('doctrine')->getRepository(User::class);
        $admin = $userRepository->findOneBy(['name' => 'admin']);
        $this->client->loginUser($admin);

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->listProcessor = self::getContainer()->get(ListStructuralElementsProcessor::class);
        $this->getProcessor = self::getContainer()->get(GetStructuralElementDetailsProcessor::class);
    }

    private function getOperationForClass(string $class): Get
    {
        return (new Get())->withClass($class);
    }

    public function testListReturnsAllElementsWithoutKeyword(): void
    {
        $result = $this->listProcessor->process(new StructuralElementSearchInput(), $this->getOperationForClass(Category::class));

        //DataStructureFixtures creates 7 category nodes ("Node 1" .. "Node 1.1.1")
        self::assertGreaterThanOrEqual(7, count($result));
        foreach ($result as $overview) {
            self::assertInstanceOf(StructuralElementOverview::class, $overview);
            //The list tool is a lean id+name overview only
            self::assertIsInt($overview->id);
            self::assertIsString($overview->name);
        }
    }

    public function testListFiltersByKeyword(): void
    {
        $result = $this->listProcessor->process(
            new StructuralElementSearchInput(keyword: 'Node 3'),
            $this->getOperationForClass(Category::class)
        );

        self::assertCount(1, $result);
        self::assertSame('Node 3', $result[0]->name);

        $node3 = $this->em->getRepository(Category::class)->findOneBy(['name' => 'Node 3']);
        self::assertSame($node3->getID(), $result[0]->id);
    }

    public function testListIsGenericAcrossEntityClasses(): void
    {
        $result = $this->listProcessor->process(
            new StructuralElementSearchInput(keyword: 'Node 3'),
            $this->getOperationForClass(Footprint::class)
        );

        self::assertCount(1, $result);
        self::assertInstanceOf(StructuralElementOverview::class, $result[0]);
        self::assertSame('Node 3', $result[0]->name);
    }

    public function testListRejectsNonStructuralResourceClass(): void
    {
        $this->expectException(\LogicException::class);
        $this->listProcessor->process(new StructuralElementSearchInput(), $this->getOperationForClass(Part::class));
    }

    public function testGetDetailsReturnsElement(): void
    {
        $node1 = $this->em->getRepository(Category::class)->findOneBy(['name' => 'Node 1']);
        self::assertNotNull($node1);

        $result = $this->getProcessor->process(new ElementByIdInput($node1->getID()), $this->getOperationForClass(Category::class));

        self::assertSame($node1->getID(), $result->getID());
        self::assertSame('Node 1', $result->getName());
    }

    public function testGetDetailsThrowsNotFoundForUnknownId(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->getProcessor->process(new ElementByIdInput(999999999), $this->getOperationForClass(Category::class));
    }

    public function testGetDetailsRejectsNonStructuralResourceClass(): void
    {
        $part = $this->em->getRepository(Part::class)->findOneBy([]);
        self::assertNotNull($part);

        $this->expectException(\LogicException::class);
        $this->getProcessor->process(new ElementByIdInput($part->getID()), $this->getOperationForClass(Part::class));
    }

    public function testGetDetailsDeniesAccessForUserWithoutPermission(): void
    {
        $userRepository = self::getContainer()->get('doctrine')->getRepository(User::class);
        $noread = $userRepository->findOneBy(['name' => 'noread']);
        $this->client->loginUser($noread);

        $node1 = $this->em->getRepository(Category::class)->findOneBy(['name' => 'Node 1']);

        $this->expectException(AccessDeniedException::class);
        $this->getProcessor->process(new ElementByIdInput($node1->getID()), $this->getOperationForClass(Category::class));
    }

    /**
     * Regression test: a list_X tool's output must actually serialize id+name through the real
     * pipeline (JSON-LD normalizer with the operation's own normalizationContext), not just when the
     * processor is called directly. Previously, list_X operations had no normalizationContext of their
     * own, so they silently inherited the backing entity's REST "Read" groups (e.g. "category:read"),
     * which don't match StructuralElementOverview's plain properties, and every field was serialized
     * away, leaving an empty object.
     *
     */
    #[DataProvider('provideListToolClasses')]
    public function testListToolOutputActuallySerializesIdAndName(string $entityClass, string $toolName): void
    {
        $resourceMetadataCollectionFactory = self::getContainer()->get(ResourceMetadataCollectionFactoryInterface::class);
        $metadata = $resourceMetadataCollectionFactory->create($entityClass);

        $operation = null;
        foreach ($metadata as $resource) {
            foreach ($resource->getMcp() ?? [] as $mcp) {
                if ($mcp instanceof McpTool && $mcp->getName() === $toolName) {
                    $operation = $mcp;
                }
            }
        }
        self::assertNotNull($operation, sprintf('mcp tool "%s" not found on %s', $toolName, $entityClass));

        $serializer = self::getContainer()->get(SerializerInterface::class);
        $overview = new StructuralElementOverview(id: 123, name: 'Regression test node');

        $result = $serializer->serialize([$overview], 'jsonld', [
            'groups' => $operation->getNormalizationContext()['groups'] ?? null,
            'resource_class' => $entityClass,
            'operation' => $operation,
        ]);

        $decoded = json_decode($result, true, flags: \JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('hydra:member', $decoded);
        self::assertSame(123, $decoded['hydra:member'][0]['id'] ?? null);
        self::assertSame('Regression test node', $decoded['hydra:member'][0]['name'] ?? null);
        //The list_X tools are a lean id+name overview - no JSON-LD "@id"/"@type" clutter per item
        self::assertArrayNotHasKey('@id', $decoded['hydra:member'][0]);
        self::assertArrayNotHasKey('@type', $decoded['hydra:member'][0]);
    }

    /**
     * @return iterable<string, array{0: class-string, 1: string}>
     */
    public static function provideListToolClasses(): iterable
    {
        yield 'categories' => [Category::class, 'list_categories'];
        yield 'footprints' => [Footprint::class, 'list_footprints'];
        yield 'manufacturers' => [Manufacturer::class, 'list_manufacturers'];
        yield 'storage_locations' => [StorageLocation::class, 'list_storage_locations'];
        yield 'suppliers' => [Supplier::class, 'list_suppliers'];
        yield 'measurement_units' => [MeasurementUnit::class, 'list_measurement_units'];
        yield 'part_custom_states' => [PartCustomState::class, 'list_part_custom_states'];
    }
}
