<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\StorageLocation;
use App\Entity\Parts\Supplier;
use App\Entity\UserSystem\User;
use App\Entity\BulkInfoProviderImportJob;
use App\Entity\BulkImportJobStatus;
use App\Services\InfoProviderSystem\DTOs\BulkSearchResponseDTO;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group slow
 * @group DB
 */
class PartControllerTest extends WebTestCase
{
    public function testShowPart(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $partRepository = $entityManager->getRepository(Part::class);
        $part = $partRepository->find(1);

        if (!$part) {
            $this->markTestSkipped('Test part with ID 1 not found in fixtures');
        }

        $client->request('GET', '/en/part/' . $part->getId());

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testShowPartWithTimestamp(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $partRepository = $entityManager->getRepository(Part::class);
        $part = $partRepository->find(1);

        if (!$part) {
            $this->markTestSkipped('Test part with ID 1 not found in fixtures');
        }

        $timestamp = time();
        $client->request('GET', "/en/part/{$part->getId()}/info/{$timestamp}");

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testEditPart(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $partRepository = $entityManager->getRepository(Part::class);
        $part = $partRepository->find(1);

        if (!$part) {
            $this->markTestSkipped('Test part with ID 1 not found in fixtures');
        }

        $client->request('GET', '/en/part/' . $part->getId() . '/edit');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSelectorExists('form[name="part_base"]');
    }

    public function testEditPartWithBulkJob(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $partRepository = $entityManager->getRepository(Part::class);
        $part = $partRepository->find(1);

        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['name' => 'admin']);

        if (!$part || !$user) {
            $this->markTestSkipped('Required test data not found in fixtures');
        }

        // Create a bulk job
        $job = new BulkInfoProviderImportJob();
        $job->setCreatedBy($user);
        $job->setPartIds([$part->getId()]);
        $job->setStatus(BulkImportJobStatus::IN_PROGRESS);
        $job->setSearchResults(new BulkSearchResponseDTO([]));

        $entityManager->persist($job);
        $entityManager->flush();

        $client->request('GET', '/en/part/' . $part->getId() . '/edit?jobId=' . $job->getId());

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        // Clean up
        $entityManager->remove($job);
        $entityManager->flush();
    }



    public function testNewPart(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $client->request('GET', '/en/part/new');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSelectorExists('form[name="part_base"]');
    }

    public function testNewPartWithCategory(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $categoryRepository = $entityManager->getRepository(Category::class);
        $category = $categoryRepository->find(1);

        if (!$category) {
            $this->markTestSkipped('Test category with ID 1 not found in fixtures');
        }

        $client->request('GET', '/en/part/new?category=' . $category->getId());

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testNewPartWithFootprint(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $footprintRepository = $entityManager->getRepository(Footprint::class);
        $footprint = $footprintRepository->find(1);

        if (!$footprint) {
            $this->markTestSkipped('Test footprint with ID 1 not found in fixtures');
        }

        $client->request('GET', '/en/part/new?footprint=' . $footprint->getId());

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testNewPartWithManufacturer(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $manufacturerRepository = $entityManager->getRepository(Manufacturer::class);
        $manufacturer = $manufacturerRepository->find(1);

        if (!$manufacturer) {
            $this->markTestSkipped('Test manufacturer with ID 1 not found in fixtures');
        }

        $client->request('GET', '/en/part/new?manufacturer=' . $manufacturer->getId());

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testNewPartWithStorageLocation(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $storageLocationRepository = $entityManager->getRepository(StorageLocation::class);
        $storageLocation = $storageLocationRepository->find(1);

        if (!$storageLocation) {
            $this->markTestSkipped('Test storage location with ID 1 not found in fixtures');
        }

        $client->request('GET', '/en/part/new?storelocation=' . $storageLocation->getId());

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testNewPartWithSupplier(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $supplierRepository = $entityManager->getRepository(Supplier::class);
        $supplier = $supplierRepository->find(1);

        if (!$supplier) {
            $this->markTestSkipped('Test supplier with ID 1 not found in fixtures');
        }

        $client->request('GET', '/en/part/new?supplier=' . $supplier->getId());

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testClonePart(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $partRepository = $entityManager->getRepository(Part::class);
        $part = $partRepository->find(1);

        if (!$part) {
            $this->markTestSkipped('Test part with ID 1 not found in fixtures');
        }

        $client->request('GET', '/en/part/' . $part->getId() . '/clone');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSelectorExists('form[name="part_base"]');
    }

    public function testMergeParts(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $categoryRepository = $entityManager->getRepository(Category::class);
        $category = $categoryRepository->find(1);

        if (!$category) {
            $this->markTestSkipped('Test category with ID 1 not found in fixtures');
        }

        // Create two test parts
        $targetPart = new Part();
        $targetPart->setName('Target Part');
        $targetPart->setCategory($category);

        $otherPart = new Part();
        $otherPart->setName('Other Part');
        $otherPart->setCategory($category);

        $entityManager->persist($targetPart);
        $entityManager->persist($otherPart);
        $entityManager->flush();

        $client->request('GET', "/en/part/{$targetPart->getId()}/merge/{$otherPart->getId()}");

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSelectorExists('form[name="part_base"]');

        // Clean up
        $entityManager->remove($targetPart);
        $entityManager->remove($otherPart);
        $entityManager->flush();
    }





    public function testAccessControlForUnauthorizedUser(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'noread');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $partRepository = $entityManager->getRepository(Part::class);
        $part = $partRepository->find(1);

        if (!$part) {
            $this->markTestSkipped('Test part with ID 1 not found in fixtures');
        }

        $client->request('GET', '/en/part/' . $part->getId());

        // Should either be forbidden or redirected to error page
        $this->assertTrue(
            $client->getResponse()->getStatusCode() === Response::HTTP_FORBIDDEN ||
            $client->getResponse()->isRedirect()
        );
    }

    private function loginAsUser($client, string $username): void
    {
        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['name' => $username]);

        if (!$user) {
            $this->markTestSkipped("User {$username} not found");
        }

        $client->loginUser($user);
    }

}
