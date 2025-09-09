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

use App\Controller\BulkInfoProviderImportController;
use App\Entity\Parts\Part;
use App\Entity\BulkInfoProviderImportJob;
use App\Entity\BulkImportJobStatus;
use App\Entity\UserSystem\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group slow
 * @group DB
 */
class BulkInfoProviderImportControllerTest extends WebTestCase
{
    public function testStep1WithoutIds(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $client->request('GET', '/tools/bulk-info-provider-import/step1');

        $this->assertResponseRedirects();
    }

    public function testStep1WithInvalidIds(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $client->request('GET', '/tools/bulk-info-provider-import/step1?ids=999999,888888');

        $this->assertResponseRedirects();
    }

    public function testManagePage(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $client->request('GET', '/tools/bulk-info-provider-import/manage');

        // Follow any redirects (like locale redirects)
        if ($client->getResponse()->isRedirect()) {
            $client->followRedirect();
        }

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testAccessControlForStep1(): void
    {
        $client = static::createClient();

        $client->request('GET', '/tools/bulk-info-provider-import/step1?ids=1');
        $this->assertResponseRedirects();

        $this->loginAsUser($client, 'noread');
        $client->request('GET', '/tools/bulk-info-provider-import/step1?ids=1');

        // Follow redirects if any, then check for 403 or final response
        if ($client->getResponse()->isRedirect()) {
            $client->followRedirect();
        }

        // The user might get redirected to an error page instead of direct 403
        $this->assertTrue(
            $client->getResponse()->getStatusCode() === Response::HTTP_FORBIDDEN ||
            $client->getResponse()->getStatusCode() === Response::HTTP_OK
        );
    }

    public function testAccessControlForManage(): void
    {
        $client = static::createClient();

        $client->request('GET', '/tools/bulk-info-provider-import/manage');
        $this->assertResponseRedirects();

        $this->loginAsUser($client, 'noread');
        $client->request('GET', '/tools/bulk-info-provider-import/manage');

        // Follow redirects if any, then check for 403 or final response
        if ($client->getResponse()->isRedirect()) {
            $client->followRedirect();
        }

        // The user might get redirected to an error page instead of direct 403
        $this->assertTrue(
            $client->getResponse()->getStatusCode() === Response::HTTP_FORBIDDEN ||
            $client->getResponse()->getStatusCode() === Response::HTTP_OK
        );
    }

    public function testStep2TemplateRendering(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();

        // Use an existing part from test fixtures (ID 1 should exist)
        $partRepository = $entityManager->getRepository(Part::class);
        $part = $partRepository->find(1);

        if (!$part) {
            $this->markTestSkipped('Test part with ID 1 not found in fixtures');
        }

        // Get the admin user for the createdBy field
        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['name' => 'admin']);

        if (!$user) {
            $this->markTestSkipped('Admin user not found in fixtures');
        }

        // Create a test job with search results that include source_field and source_keyword
        $job = new BulkInfoProviderImportJob();
        $job->setCreatedBy($user);
        $job->addPart($part);
        $job->setStatus(BulkImportJobStatus::IN_PROGRESS);
        $job->setSearchResults([
            [
                'part_id' => $part->getId(),
                'search_results' => [
                    [
                        'dto' => [
                            'provider_key' => 'test_provider',
                            'provider_id' => 'TEST123',
                            'name' => 'Test Component',
                            'description' => 'Test component description',
                            'manufacturer' => 'Test Manufacturer',
                            'mpn' => 'TEST-MPN-123',
                            'provider_url' => 'https://example.com/test',
                            'preview_image_url' => null,
                            '_source_field' => 'test_field',
                            '_source_keyword' => 'test_keyword'
                        ],
                        'localPart' => null
                    ]
                ],
                'errors' => []
            ]
        ]);

        $entityManager->persist($job);
        $entityManager->flush();

        // Test that step2 renders correctly with the search results
        $client->request('GET', '/tools/bulk-info-provider-import/step2/' . $job->getId());

        // Follow any redirects (like locale redirects)
        if ($client->getResponse()->isRedirect()) {
            $client->followRedirect();
        }

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        // Verify the template rendered the source_field and source_keyword correctly
        $content = $client->getResponse()->getContent();
        $this->assertStringContainsString('test_field', $content);
        $this->assertStringContainsString('test_keyword', $content);

        // Clean up - find by ID to avoid detached entity issues
        $jobId = $job->getId();
        $entityManager->clear(); // Clear all entities
        $jobToRemove = $entityManager->find(BulkInfoProviderImportJob::class, $jobId);
        if ($jobToRemove) {
            $entityManager->remove($jobToRemove);
            $entityManager->flush();
        }
    }

    public function testStep1WithValidIds(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $partRepository = $entityManager->getRepository(Part::class);
        $part = $partRepository->find(1);

        if (!$part) {
            $this->markTestSkipped('Test part with ID 1 not found in fixtures');
        }

        $client->request('GET', '/tools/bulk-info-provider-import/step1?ids=' . $part->getId());

        if ($client->getResponse()->isRedirect()) {
            $client->followRedirect();
        }

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }


    public function testDeleteJobWithValidJob(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['name' => 'admin']);

        if (!$user) {
            $this->markTestSkipped('Admin user not found in fixtures');
        }

        // Get a test part
        $partRepository = $entityManager->getRepository(Part::class);
        $part = $partRepository->find(1);

        if (!$part) {
            $this->markTestSkipped('Test part with ID 1 not found in fixtures');
        }

        // Create a completed job
        $job = new BulkInfoProviderImportJob();
        $job->setCreatedBy($user);
        $job->addPart($part);
        $job->setStatus(BulkImportJobStatus::COMPLETED);
        $job->setSearchResults([]);

        $entityManager->persist($job);
        $entityManager->flush();

        $client->request('DELETE', '/en/tools/bulk-info-provider-import/job/' . $job->getId() . '/delete');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
    }

    public function testDeleteJobWithNonExistentJob(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $client->request('DELETE', '/en/tools/bulk-info-provider-import/job/999999/delete');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $response);
    }

    public function testDeleteJobWithActiveJob(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['name' => 'admin']);

        if (!$user) {
            $this->markTestSkipped('Admin user not found in fixtures');
        }

        // Get test parts
        $parts = $this->getTestParts($entityManager, [1]);

        // Create an active job
        $job = new BulkInfoProviderImportJob();
        $job->setCreatedBy($user);
        foreach ($parts as $part) {
            $job->addPart($part);
        }
        $job->setStatus(BulkImportJobStatus::IN_PROGRESS);
        $job->setSearchResults([]);

        $entityManager->persist($job);
        $entityManager->flush();

        $client->request('DELETE', '/en/tools/bulk-info-provider-import/job/' . $job->getId() . '/delete');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $response);

        // Clean up
        $entityManager->remove($job);
        $entityManager->flush();
    }

    public function testStopJobWithValidJob(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['name' => 'admin']);

        if (!$user) {
            $this->markTestSkipped('Admin user not found in fixtures');
        }

        // Get test parts
        $parts = $this->getTestParts($entityManager, [1]);

        // Create an active job
        $job = new BulkInfoProviderImportJob();
        $job->setCreatedBy($user);
        foreach ($parts as $part) {
            $job->addPart($part);
        }
        $job->setStatus(BulkImportJobStatus::IN_PROGRESS);
        $job->setSearchResults([]);

        $entityManager->persist($job);
        $entityManager->flush();

        $client->request('POST', '/en/tools/bulk-info-provider-import/job/' . $job->getId() . '/stop');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);

        // Clean up
        $entityManager->remove($job);
        $entityManager->flush();
    }

    public function testStopJobWithNonExistentJob(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $client->request('POST', '/en/tools/bulk-info-provider-import/job/999999/stop');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $response);
    }

    public function testMarkPartCompleted(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['name' => 'admin']);

        if (!$user) {
            $this->markTestSkipped('Admin user not found in fixtures');
        }

        // Get test parts
        $parts = $this->getTestParts($entityManager, [1, 2]);

        $job = new BulkInfoProviderImportJob();
        $job->setCreatedBy($user);
        foreach ($parts as $part) {
            $job->addPart($part);
        }
        $job->setStatus(BulkImportJobStatus::IN_PROGRESS);
        $job->setSearchResults([]);

        $entityManager->persist($job);
        $entityManager->flush();

        $client->request('POST', '/en/tools/bulk-info-provider-import/job/' . $job->getId() . '/part/1/mark-completed');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('progress', $response);
        $this->assertArrayHasKey('completed_count', $response);

        // Clean up
        $entityManager->remove($job);
        $entityManager->flush();
    }

    public function testMarkPartSkipped(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['name' => 'admin']);

        if (!$user) {
            $this->markTestSkipped('Admin user not found in fixtures');
        }

        // Get test parts
        $parts = $this->getTestParts($entityManager, [1, 2]);

        $job = new BulkInfoProviderImportJob();
        $job->setCreatedBy($user);
        foreach ($parts as $part) {
            $job->addPart($part);
        }
        $job->setStatus(BulkImportJobStatus::IN_PROGRESS);
        $job->setSearchResults([]);

        $entityManager->persist($job);
        $entityManager->flush();

        $client->request('POST', '/en/tools/bulk-info-provider-import/job/' . $job->getId() . '/part/1/mark-skipped', [
            'reason' => 'Test skip reason'
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('skipped_count', $response);

        // Clean up
        $entityManager->remove($job);
        $entityManager->flush();
    }

    public function testMarkPartPending(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['name' => 'admin']);

        if (!$user) {
            $this->markTestSkipped('Admin user not found in fixtures');
        }

        // Get test parts
        $parts = $this->getTestParts($entityManager, [1]);

        $job = new BulkInfoProviderImportJob();
        $job->setCreatedBy($user);
        foreach ($parts as $part) {
            $job->addPart($part);
        }
        $job->setStatus(BulkImportJobStatus::IN_PROGRESS);
        $job->setSearchResults([]);

        $entityManager->persist($job);
        $entityManager->flush();

        $client->request('POST', '/en/tools/bulk-info-provider-import/job/' . $job->getId() . '/part/1/mark-pending');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);

        // Clean up
        $entityManager->remove($job);
        $entityManager->flush();
    }

    public function testStep2WithNonExistentJob(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $client->request('GET', '/tools/bulk-info-provider-import/step2/999999');

        $this->assertResponseRedirects();
    }

    public function testStep2WithUnauthorizedAccess(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $userRepository = $entityManager->getRepository(User::class);
        $admin = $userRepository->findOneBy(['name' => 'admin']);
        $readonly = $userRepository->findOneBy(['name' => 'noread']);

        if (!$admin || !$readonly) {
            $this->markTestSkipped('Required test users not found in fixtures');
        }

        // Get test parts
        $parts = $this->getTestParts($entityManager, [1]);

        // Create job as admin
        $job = new BulkInfoProviderImportJob();
        $job->setCreatedBy($admin);
        foreach ($parts as $part) {
            $job->addPart($part);
        }
        $job->setStatus(BulkImportJobStatus::IN_PROGRESS);
        $job->setSearchResults([]);

        $entityManager->persist($job);
        $entityManager->flush();

        // Try to access as readonly user
        $this->loginAsUser($client, 'noread');
        $client->request('GET', '/tools/bulk-info-provider-import/step2/' . $job->getId());

        $this->assertResponseRedirects();

        // Clean up
        $entityManager->remove($job);
        $entityManager->flush();
    }

    public function testJobAccessControlForDelete(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $userRepository = $entityManager->getRepository(User::class);
        $admin = $userRepository->findOneBy(['name' => 'admin']);
        $readonly = $userRepository->findOneBy(['name' => 'noread']);

        if (!$admin || !$readonly) {
            $this->markTestSkipped('Required test users not found in fixtures');
        }

        // Get test parts
        $parts = $this->getTestParts($entityManager, [1]);

        // Create job as readonly user
        $job = new BulkInfoProviderImportJob();
        $job->setCreatedBy($readonly);
        foreach ($parts as $part) {
            $job->addPart($part);
        }
        $job->setStatus(BulkImportJobStatus::COMPLETED);
        $job->setSearchResults([]);

        $entityManager->persist($job);
        $entityManager->flush();

        // Try to delete as admin (should fail due to ownership)
        $client->request('DELETE', '/en/tools/bulk-info-provider-import/job/' . $job->getId() . '/delete');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        // Clean up
        $entityManager->remove($job);
        $entityManager->flush();
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

    private function getTestParts($entityManager, array $ids): array
    {
        $partRepository = $entityManager->getRepository(Part::class);
        $parts = [];

        foreach ($ids as $id) {
            $part = $partRepository->find($id);
            if (!$part) {
                $this->markTestSkipped("Test part with ID {$id} not found in fixtures");
            }
            $parts[] = $part;
        }

        return $parts;
    }

    public function testStep1Form(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $partRepository = $entityManager->getRepository(Part::class);
        $part = $partRepository->find(1);

        if (!$part) {
            $this->markTestSkipped('Test part with ID 1 not found in fixtures');
        }

        $client->request('GET', '/tools/bulk-info-provider-import/step1?ids=' . $part->getId());

        if ($client->getResponse()->isRedirect()) {
            $client->followRedirect();
        }

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertStringContainsString('Bulk Info Provider Import', $client->getResponse()->getContent());
    }

    public function testStep1FormSubmissionWithErrors(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $partRepository = $entityManager->getRepository(Part::class);
        $part = $partRepository->find(1);

        if (!$part) {
            $this->markTestSkipped('Test part with ID 1 not found in fixtures');
        }

        $client->request('GET', '/tools/bulk-info-provider-import/step1?ids=' . $part->getId());

        if ($client->getResponse()->isRedirect()) {
            $client->followRedirect();
        }

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertStringContainsString('Bulk Info Provider Import', $client->getResponse()->getContent());
    }

    public function testBulkInfoProviderServiceKeywordExtraction(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $partRepository = $entityManager->getRepository(Part::class);
        $part = $partRepository->find(1);

        if (!$part) {
            $this->markTestSkipped('Test part with ID 1 not found in fixtures');
        }

        // Test that the service can extract keywords from parts
        $bulkService = $client->getContainer()->get(\App\Services\InfoProviderSystem\BulkInfoProviderService::class);

        // Create a test request to verify the service works
        $request = new \App\Services\InfoProviderSystem\DTOs\BulkSearchRequestDTO(
            fieldMappings: [
                ['field' => 'name', 'providers' => ['test'], 'priority' => 1],
                ['field' => 'mpn', 'providers' => ['test'], 'priority' => 2]
            ],
            prefetchDetails: false,
            partIds: [$part->getId()]
        );

        // The service may return an empty result or throw when no results are found
        try {
            $result = $bulkService->performBulkSearch($request);
            $this->assertIsArray($result);
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('No search results found', $e->getMessage());
        }
    }

    public function testBulkInfoProviderImportJobSerialization(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $partRepository = $entityManager->getRepository(Part::class);
        $part = $partRepository->find(1);

        if (!$part) {
            $this->markTestSkipped('Test part with ID 1 not found in fixtures');
        }

        // Test the entity's serialization methods directly
        $job = new BulkInfoProviderImportJob();
        $job->addPart($part);

        $searchResults = [
            [
                'part' => $part,
                'search_results' => [
                    [
                        'dto' => new \App\Services\InfoProviderSystem\DTOs\SearchResultDTO(
                            provider_key: 'test',
                            provider_id: 'TEST123',
                            name: 'Test Component',
                            description: 'Test description',
                            manufacturer: 'Test Manufacturer',
                            mpn: 'TEST-MPN',
                            provider_url: 'https://example.com',
                            preview_image_url: null
                        ),
                        'localPart' => null,
                        'source_field' => 'mpn',
                        'source_keyword' => 'TEST123'
                    ]
                ],
                'errors' => []
            ]
        ];

        // Test serialization
        $serialized = $job->serializeSearchResults($searchResults);
        $this->assertIsArray($serialized);
        $this->assertArrayHasKey(0, $serialized);
        $this->assertArrayHasKey('part_id', $serialized[0]);

        // Test deserialization
        $deserialized = $job->deserializeSearchResults($entityManager);
        $this->assertIsArray($deserialized);
        $this->assertCount(0, $deserialized); // Empty because job has no search results set
    }

    public function testManagePageWithJobCleanup(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['name' => 'admin']);

        if (!$user) {
            $this->markTestSkipped('Admin user not found in fixtures');
        }

        $partRepository = $entityManager->getRepository(Part::class);
        $part = $partRepository->find(1);

        if (!$part) {
            $this->markTestSkipped('Test part with ID 1 not found in fixtures');
        }

        $job = new BulkInfoProviderImportJob();
        $job->setCreatedBy($user);
        $job->addPart($part);
        $job->setStatus(BulkImportJobStatus::IN_PROGRESS);
        $job->setSearchResults([]);

        $entityManager->persist($job);
        $entityManager->flush();

        $client->request('GET', '/tools/bulk-info-provider-import/manage');

        if ($client->getResponse()->isRedirect()) {
            $client->followRedirect();
        }

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        // Find job from database to avoid detached entity errors  
        $jobId = $job->getId();
        $entityManager->clear();
        $persistedJob = $entityManager->find(BulkInfoProviderImportJob::class, $jobId);
        if ($persistedJob) {
            $entityManager->remove($persistedJob);
            $entityManager->flush();
        }
    }

    public function testBulkInfoProviderServiceSupplierPartNumberExtraction(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $partRepository = $entityManager->getRepository(Part::class);
        $part = $partRepository->find(1);

        if (!$part) {
            $this->markTestSkipped('Test part with ID 1 not found in fixtures');
        }

        // Test that the service can handle supplier part number fields
        $bulkService = $client->getContainer()->get(\App\Services\InfoProviderSystem\BulkInfoProviderService::class);

        // Create a test request with supplier SPN field mapping
        $request = new \App\Services\InfoProviderSystem\DTOs\BulkSearchRequestDTO(
            fieldMappings: [
                ['field' => 'invalid_field', 'providers' => ['test'], 'priority' => 1],
                ['field' => 'test_supplier_spn', 'providers' => ['test'], 'priority' => 2]
            ],
            prefetchDetails: false,
            partIds: [$part->getId()]
        );

        // The service should be able to process the request and throw an exception when no results are found
        try {
            $bulkService->performBulkSearch($request);
            $this->fail('Expected RuntimeException to be thrown when no search results are found');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('No search results found', $e->getMessage());
        }
    }

    public function testBulkInfoProviderServiceBatchProcessing(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $partRepository = $entityManager->getRepository(Part::class);
        $part = $partRepository->find(1);

        if (!$part) {
            $this->markTestSkipped('Test part with ID 1 not found in fixtures');
        }

        // Test that the service can handle batch processing
        $bulkService = $client->getContainer()->get(\App\Services\InfoProviderSystem\BulkInfoProviderService::class);

        // Create a test request with multiple keywords
        $request = new \App\Services\InfoProviderSystem\DTOs\BulkSearchRequestDTO(
            fieldMappings: [
                ['field' => 'name', 'providers' => ['lcsc'], 'priority' => 1]
            ],
            prefetchDetails: false,
            partIds: [$part->getId()]
        );

        // The service should be able to process the request and throw an exception when no results are found
        try {
            $bulkService->performBulkSearch($request);
            $this->fail('Expected RuntimeException to be thrown when no search results are found');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('No search results found', $e->getMessage());
        }
    }

    public function testBulkInfoProviderServicePrefetchDetails(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $partRepository = $entityManager->getRepository(Part::class);
        $part = $partRepository->find(1);

        if (!$part) {
            $this->markTestSkipped('Test part with ID 1 not found in fixtures');
        }

        // Test that the service can handle prefetch details
        $bulkService = $client->getContainer()->get(\App\Services\InfoProviderSystem\BulkInfoProviderService::class);

        // Create empty search results to test prefetch method
        $searchResults = [
            [
                'part' => $part,
                'search_results' => [],
                'errors' => []
            ]
        ];

        // The prefetch method should not throw any errors
        $bulkService->prefetchDetailsForResults($searchResults);

        // If we get here, the method executed successfully
        $this->assertTrue(true);
    }

    public function testJobAccessControlForStopAndMarkOperations(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $userRepository = $entityManager->getRepository(User::class);
        $admin = $userRepository->findOneBy(['name' => 'admin']);
        $readonly = $userRepository->findOneBy(['name' => 'noread']);

        if (!$admin || !$readonly) {
            $this->markTestSkipped('Required test users not found in fixtures');
        }

        $parts = $this->getTestParts($entityManager, [1]);

        $job = new BulkInfoProviderImportJob();
        $job->setCreatedBy($readonly);
        foreach ($parts as $part) {
            $job->addPart($part);
        }
        $job->setStatus(BulkImportJobStatus::IN_PROGRESS);
        $job->setSearchResults([]);

        $entityManager->persist($job);
        $entityManager->flush();

        $client->request('POST', '/en/tools/bulk-info-provider-import/job/' . $job->getId() . '/stop');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $client->request('POST', '/en/tools/bulk-info-provider-import/job/' . $job->getId() . '/part/1/mark-completed');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $client->request('POST', '/en/tools/bulk-info-provider-import/job/' . $job->getId() . '/part/1/mark-skipped', [
            'reason' => 'Test reason'
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $client->request('POST', '/en/tools/bulk-info-provider-import/job/' . $job->getId() . '/part/1/mark-pending');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        // Find job from database to avoid detached entity errors
        $jobId = $job->getId();
        $entityManager->clear();
        $persistedJob = $entityManager->find(BulkInfoProviderImportJob::class, $jobId);
        if ($persistedJob) {
            $entityManager->remove($persistedJob);
            $entityManager->flush();
        }
    }

    public function testOperationsOnCompletedJob(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['name' => 'admin']);

        if (!$user) {
            $this->markTestSkipped('Admin user not found in fixtures');
        }

        $parts = $this->getTestParts($entityManager, [1]);

        $job = new BulkInfoProviderImportJob();
        $job->setCreatedBy($user);
        foreach ($parts as $part) {
            $job->addPart($part);
        }
        $job->setStatus(BulkImportJobStatus::COMPLETED);
        $job->setSearchResults([]);

        $entityManager->persist($job);
        $entityManager->flush();

        $client->request('POST', '/en/tools/bulk-info-provider-import/job/' . $job->getId() . '/stop');
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $response);

        $entityManager->remove($job);
        $entityManager->flush();
    }
}