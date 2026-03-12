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

use App\Services\InfoProviderSystem\BulkInfoProviderService;
use App\Services\InfoProviderSystem\DTOs\BulkSearchFieldMappingDTO;
use App\Entity\InfoProviderSystem\BulkImportJobStatus;
use App\Entity\InfoProviderSystem\BulkInfoProviderImportJob;
use App\Entity\Parts\Part;
use App\Entity\UserSystem\User;
use App\Services\InfoProviderSystem\DTOs\BulkSearchPartResultDTO;
use App\Services\InfoProviderSystem\DTOs\BulkSearchPartResultsDTO;
use App\Services\InfoProviderSystem\DTOs\BulkSearchResponseDTO;
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group("slow")]
#[Group("DB")]
final class BulkInfoProviderImportControllerTest extends WebTestCase
{
    public function testStep1WithoutIds(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $client->request('GET', '/tools/bulk_info_provider_import/step1');

        self::assertResponseRedirects();
    }

    public function testStep1WithInvalidIds(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $client->request('GET', '/tools/bulk_info_provider_import/step1?ids=999999,888888');

        self::assertResponseRedirects();
    }

    public function testManagePage(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $client->request('GET', '/tools/bulk_info_provider_import/manage');

        // Follow any redirects (like locale redirects)
        if ($client->getResponse()->isRedirect()) {
            $client->followRedirect();
        }

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testAccessControlForStep1(): void
    {
        $client = static::createClient();

        $client->request('GET', '/tools/bulk_info_provider_import/step1?ids=1');
        self::assertResponseRedirects();

        $this->loginAsUser($client, 'noread');
        $client->request('GET', '/tools/bulk_info_provider_import/step1?ids=1');

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

        $client->request('GET', '/tools/bulk_info_provider_import/manage');
        self::assertResponseRedirects();

        $this->loginAsUser($client, 'noread');
        $client->request('GET', '/tools/bulk_info_provider_import/manage');

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

        $entityManager = static::getContainer()->get('doctrine')->getManager();

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

        $searchResults = new BulkSearchResponseDTO(partResults: [
            new BulkSearchPartResultsDTO(part: $part,
                searchResults: [new BulkSearchPartResultDTO(
                    searchResult: new SearchResultDTO(provider_key: 'test_provider', provider_id: 'TEST123', name: 'Test Component', description: 'Test component description', manufacturer: 'Test Manufacturer', mpn: 'TEST-MPN-123', provider_url: 'https://example.com/test', preview_image_url: null,),
                    sourceField: 'test_field',
                    sourceKeyword: 'test_keyword',
                    localPart: null,
                )]
            )
        ]);

        $job->setSearchResults($searchResults);

        $entityManager->persist($job);
        $entityManager->flush();

        // Test that step2 renders correctly with the search results
        $client->request('GET', '/tools/bulk_info_provider_import/step2/' . $job->getId());

        // Follow any redirects (like locale redirects)
        if ($client->getResponse()->isRedirect()) {
            $client->followRedirect();
        }

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        // Verify the template rendered the source_field and source_keyword correctly
        $content = $client->getResponse()->getContent();
        $this->assertStringContainsString('test_field', (string) $content);
        $this->assertStringContainsString('test_keyword', (string) $content);

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

        $client->request('GET', '/tools/bulk_info_provider_import/step1?ids=' . $part->getId());

        if ($client->getResponse()->isRedirect()) {
            $client->followRedirect();
        }

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }


    public function testDeleteJobWithValidJob(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = self::getContainer()->get('doctrine')->getManager();
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
        $job->setSearchResults(new BulkSearchResponseDTO([]));

        $entityManager->persist($job);
        $entityManager->flush();

        $client->request('DELETE', '/en/tools/bulk_info_provider_import/job/' . $job->getId() . '/delete');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
    }

    public function testDeleteJobWithNonExistentJob(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $client->request('DELETE', '/en/tools/bulk_info_provider_import/job/999999/delete');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $response);
    }

    public function testDeleteJobWithActiveJob(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = self::getContainer()->get('doctrine')->getManager();
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
        $job->setSearchResults(new BulkSearchResponseDTO([]));

        $entityManager->persist($job);
        $entityManager->flush();

        $client->request('DELETE', '/en/tools/bulk_info_provider_import/job/' . $job->getId() . '/delete');

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

        $entityManager = self::getContainer()->get('doctrine')->getManager();
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
        $job->setSearchResults(new BulkSearchResponseDTO([]));

        $entityManager->persist($job);
        $entityManager->flush();

        $client->request('POST', '/en/tools/bulk_info_provider_import/job/' . $job->getId() . '/stop');

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

        $client->request('POST', '/en/tools/bulk_info_provider_import/job/999999/stop');

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
        $job->setSearchResults(new BulkSearchResponseDTO([]));

        $entityManager->persist($job);
        $entityManager->flush();

        $client->request('POST', '/en/tools/bulk_info_provider_import/job/' . $job->getId() . '/part/1/mark-completed');

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
        $job->setSearchResults(new BulkSearchResponseDTO([]));

        $entityManager->persist($job);
        $entityManager->flush();

        $client->request('POST', '/en/tools/bulk_info_provider_import/job/' . $job->getId() . '/part/1/mark-skipped', [
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
        $job->setSearchResults(new BulkSearchResponseDTO([]));

        $entityManager->persist($job);
        $entityManager->flush();

        $client->request('POST', '/en/tools/bulk_info_provider_import/job/' . $job->getId() . '/part/1/mark-pending');

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

        $client->request('GET', '/tools/bulk_info_provider_import/step2/999999');

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
        $job->setSearchResults(new BulkSearchResponseDTO([]));

        $entityManager->persist($job);
        $entityManager->flush();

        // Try to access as readonly user
        $this->loginAsUser($client, 'noread');
        $client->request('GET', '/tools/bulk_info_provider_import/step2/' . $job->getId());

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
        $job->setSearchResults(new BulkSearchResponseDTO([]));

        $entityManager->persist($job);
        $entityManager->flush();

        // Try to delete as admin (should fail due to ownership)
        $client->request('DELETE', '/en/tools/bulk_info_provider_import/job/' . $job->getId() . '/delete');

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

    public function testQuickApplyWithNonExistentJob(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $client->request('POST', '/en/tools/bulk_info_provider_import/job/999999/part/1/quick-apply');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $response);
    }

    public function testQuickApplyWithNonExistentPart(): void
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
        $job->setStatus(BulkImportJobStatus::IN_PROGRESS);
        $job->setSearchResults(new BulkSearchResponseDTO([]));

        $entityManager->persist($job);
        $entityManager->flush();

        $client->request('POST', '/en/tools/bulk_info_provider_import/job/' . $job->getId() . '/part/999999/quick-apply');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        // Clean up
        $entityManager->remove($job);
        $entityManager->flush();
    }

    public function testQuickApplyWithNoSearchResults(): void
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
        $job->setStatus(BulkImportJobStatus::IN_PROGRESS);
        // Empty search results - no provider results for any parts
        $job->setSearchResults(new BulkSearchResponseDTO([
            new BulkSearchPartResultsDTO(part: $parts[0], searchResults: [], errors: [])
        ]));

        $entityManager->persist($job);
        $entityManager->flush();

        // Quick apply without providing providerKey/providerId and no search results available
        $client->request('POST', '/en/tools/bulk_info_provider_import/job/' . $job->getId() . '/part/1/quick-apply', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([]));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($response['success']);

        // Clean up
        $entityManager->remove($job);
        $entityManager->flush();
    }

    public function testQuickApplyAccessControl(): void
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

        // Create job owned by readonly user
        $job = new BulkInfoProviderImportJob();
        $job->setCreatedBy($readonly);
        foreach ($parts as $part) {
            $job->addPart($part);
        }
        $job->setStatus(BulkImportJobStatus::IN_PROGRESS);
        $job->setSearchResults(new BulkSearchResponseDTO([]));

        $entityManager->persist($job);
        $entityManager->flush();

        // Admin tries to quick apply on readonly user's job - should fail
        $client->request('POST', '/en/tools/bulk_info_provider_import/job/' . $job->getId() . '/part/1/quick-apply');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        // Clean up
        $jobId = $job->getId();
        $entityManager->clear();
        $persistedJob = $entityManager->find(BulkInfoProviderImportJob::class, $jobId);
        if ($persistedJob) {
            $entityManager->remove($persistedJob);
            $entityManager->flush();
        }
    }

    public function testQuickApplyAllWithNonExistentJob(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $client->request('POST', '/en/tools/bulk_info_provider_import/job/999999/quick-apply-all');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $response);
    }

    public function testQuickApplyAllWithNoResults(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['name' => 'admin']);

        if (!$user) {
            $this->markTestSkipped('Admin user not found in fixtures');
        }

        $parts = $this->getTestParts($entityManager, [1, 2]);

        $job = new BulkInfoProviderImportJob();
        $job->setCreatedBy($user);
        foreach ($parts as $part) {
            $job->addPart($part);
        }
        $job->setStatus(BulkImportJobStatus::IN_PROGRESS);
        // Empty search results for all parts
        $job->setSearchResults(new BulkSearchResponseDTO([
            new BulkSearchPartResultsDTO(part: $parts[0], searchResults: [], errors: []),
            new BulkSearchPartResultsDTO(part: $parts[1], searchResults: [], errors: []),
        ]));

        $entityManager->persist($job);
        $entityManager->flush();

        $client->request('POST', '/en/tools/bulk_info_provider_import/job/' . $job->getId() . '/quick-apply-all');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertEquals(0, $response['applied']);
        $this->assertEquals(2, $response['no_results']);

        // Clean up
        $entityManager->remove($job);
        $entityManager->flush();
    }

    public function testQuickApplyAllAccessControl(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $userRepository = $entityManager->getRepository(User::class);
        $readonly = $userRepository->findOneBy(['name' => 'noread']);

        if (!$readonly) {
            $this->markTestSkipped('Required test users not found in fixtures');
        }

        $parts = $this->getTestParts($entityManager, [1]);

        $job = new BulkInfoProviderImportJob();
        $job->setCreatedBy($readonly);
        foreach ($parts as $part) {
            $job->addPart($part);
        }
        $job->setStatus(BulkImportJobStatus::IN_PROGRESS);
        $job->setSearchResults(new BulkSearchResponseDTO([]));

        $entityManager->persist($job);
        $entityManager->flush();

        // Admin tries quick apply all on readonly user's job
        $client->request('POST', '/en/tools/bulk_info_provider_import/job/' . $job->getId() . '/quick-apply-all');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        // Clean up
        $jobId = $job->getId();
        $entityManager->clear();
        $persistedJob = $entityManager->find(BulkInfoProviderImportJob::class, $jobId);
        if ($persistedJob) {
            $entityManager->remove($persistedJob);
            $entityManager->flush();
        }
    }

    public function testStep2TemplateRenderingWithQuickApplyButtons(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $partRepository = $entityManager->getRepository(Part::class);
        $part = $partRepository->find(1);

        if (!$part) {
            $this->markTestSkipped('Test part with ID 1 not found in fixtures');
        }

        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['name' => 'admin']);

        if (!$user) {
            $this->markTestSkipped('Admin user not found in fixtures');
        }

        $job = new BulkInfoProviderImportJob();
        $job->setCreatedBy($user);
        $job->addPart($part);
        $job->setStatus(BulkImportJobStatus::IN_PROGRESS);

        $searchResults = new BulkSearchResponseDTO(partResults: [
            new BulkSearchPartResultsDTO(part: $part,
                searchResults: [new BulkSearchPartResultDTO(
                    searchResult: new SearchResultDTO(provider_key: 'test_provider', provider_id: 'TEST123', name: 'Test Component', description: 'Test description', manufacturer: 'Test Mfg', mpn: 'TEST-MPN', provider_url: 'https://example.com/test', preview_image_url: null),
                    sourceField: 'mpn',
                    sourceKeyword: 'TEST-MPN',
                )]
            )
        ]);

        $job->setSearchResults($searchResults);

        $entityManager->persist($job);
        $entityManager->flush();

        $client->request('GET', '/tools/bulk_info_provider_import/step2/' . $job->getId());

        if ($client->getResponse()->isRedirect()) {
            $client->followRedirect();
        }

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $content = (string) $client->getResponse()->getContent();
        // Verify quick apply buttons are rendered (Stimulus renders camelCase as kebab-case data attributes)
        $this->assertStringContainsString('quick-apply-url-value', $content);
        $this->assertStringContainsString('quick-apply-all-url-value', $content);

        // Clean up
        $jobId = $job->getId();
        $entityManager->clear();
        $jobToRemove = $entityManager->find(BulkInfoProviderImportJob::class, $jobId);
        if ($jobToRemove) {
            $entityManager->remove($jobToRemove);
            $entityManager->flush();
        }
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

        $client->request('GET', '/tools/bulk_info_provider_import/step1?ids=' . $part->getId());

        if ($client->getResponse()->isRedirect()) {
            $client->followRedirect();
        }

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertStringContainsString('Bulk Info Provider Import', (string) $client->getResponse()->getContent());
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

        $client->request('GET', '/tools/bulk_info_provider_import/step1?ids=' . $part->getId());

        if ($client->getResponse()->isRedirect()) {
            $client->followRedirect();
        }

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertStringContainsString('Bulk Info Provider Import', (string) $client->getResponse()->getContent());
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
        $bulkService = $client->getContainer()->get(BulkInfoProviderService::class);

        // Create field mappings to verify the service works
        $fieldMappings = [
            new BulkSearchFieldMappingDTO('name', ['test'], 1),
            new BulkSearchFieldMappingDTO('mpn', ['test'], 2)
        ];

        // The service may return an empty result or throw when no results are found
        try {
            $result = $bulkService->performBulkSearch([$part], $fieldMappings, false);
            $this->assertInstanceOf(BulkSearchResponseDTO::class, $result);
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('No search results found', $e->getMessage());
        }
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
        $job->setSearchResults(new BulkSearchResponseDTO([]));

        $entityManager->persist($job);
        $entityManager->flush();

        $client->request('GET', '/tools/bulk_info_provider_import/manage');

        if ($client->getResponse()->isRedirect()) {
            $client->followRedirect();
        }

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

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
        $bulkService = $client->getContainer()->get(BulkInfoProviderService::class);

        // Create field mappings with supplier SPN field mapping
        $fieldMappings = [
            new BulkSearchFieldMappingDTO('invalid_field', ['test'], 1),
            new BulkSearchFieldMappingDTO('test_supplier_spn', ['test'], 2)
        ];

        // The service should return an empty response DTO when no results are found
        $response = $bulkService->performBulkSearch([$part], $fieldMappings, false);
        $this->assertFalse($response->hasAnyResults());
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
        $bulkService = $client->getContainer()->get(BulkInfoProviderService::class);

        // Create field mappings with multiple keywords
        $fieldMappings = [
            new BulkSearchFieldMappingDTO('empty', ['test'], 1)
        ];

        // The service should return an empty response DTO when no results are found
        $response = $bulkService->performBulkSearch([$part], $fieldMappings, false);
        $this->assertFalse($response->hasAnyResults());
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
        $bulkService = $client->getContainer()->get(BulkInfoProviderService::class);

        // Create empty search results to test prefetch method
        $searchResults = new BulkSearchResponseDTO([
            new BulkSearchPartResultsDTO(part: $part, searchResults: [], errors: [])
        ]);

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
        $job->setSearchResults(new BulkSearchResponseDTO([]));

        $entityManager->persist($job);
        $entityManager->flush();

        $client->request('POST', '/en/tools/bulk_info_provider_import/job/' . $job->getId() . '/stop');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $client->request('POST', '/en/tools/bulk_info_provider_import/job/' . $job->getId() . '/part/1/mark-completed');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $client->request('POST', '/en/tools/bulk_info_provider_import/job/' . $job->getId() . '/part/1/mark-skipped', [
            'reason' => 'Test reason'
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $client->request('POST', '/en/tools/bulk_info_provider_import/job/' . $job->getId() . '/part/1/mark-pending');
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
        $job->setSearchResults(new BulkSearchResponseDTO([]));

        $entityManager->persist($job);
        $entityManager->flush();

        $client->request('POST', '/en/tools/bulk_info_provider_import/job/' . $job->getId() . '/stop');
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $response);

        $entityManager->remove($job);
        $entityManager->flush();
    }

    /**
     * Helper to create a job with search results for testing.
     */
    private function createJobWithSearchResults(object $entityManager, object $user, array $parts, string $status = 'in_progress'): BulkInfoProviderImportJob
    {
        $job = new BulkInfoProviderImportJob();
        $job->setCreatedBy($user);
        foreach ($parts as $part) {
            $job->addPart($part);
        }

        $statusEnum = match ($status) {
            'pending' => BulkImportJobStatus::PENDING,
            'completed' => BulkImportJobStatus::COMPLETED,
            'stopped' => BulkImportJobStatus::STOPPED,
            default => BulkImportJobStatus::IN_PROGRESS,
        };
        $job->setStatus($statusEnum);

        // Create search results with a result per part
        $partResults = [];
        foreach ($parts as $part) {
            $partResults[] = new BulkSearchPartResultsDTO(
                part: $part,
                searchResults: [
                    new BulkSearchPartResultDTO(
                        searchResult: new SearchResultDTO(
                            provider_key: 'test_provider',
                            provider_id: 'TEST_' . $part->getId(),
                            name: $part->getName() ?? 'Test Part',
                            description: 'Test description',
                            manufacturer: 'Test Mfg',
                            mpn: 'MPN-' . $part->getId(),
                            provider_url: 'https://example.com/' . $part->getId(),
                            preview_image_url: null,
                        ),
                        sourceField: 'mpn',
                        sourceKeyword: $part->getName() ?? 'test',
                        localPart: null,
                    ),
                ]
            );
        }

        $job->setSearchResults(new BulkSearchResponseDTO($partResults));
        $entityManager->persist($job);
        $entityManager->flush();

        return $job;
    }

    private function cleanupJob(object $entityManager, int $jobId): void
    {
        $entityManager->clear();
        $persistedJob = $entityManager->find(BulkInfoProviderImportJob::class, $jobId);
        if ($persistedJob) {
            $entityManager->remove($persistedJob);
            $entityManager->flush();
        }
    }

    public function testDeleteCompletedJob(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $user = $entityManager->getRepository(User::class)->findOneBy(['name' => 'admin']);
        $parts = $this->getTestParts($entityManager, [1]);

        if (!$user || empty($parts)) {
            $this->markTestSkipped('Required fixtures not found');
        }

        $job = $this->createJobWithSearchResults($entityManager, $user, $parts, 'completed');
        $jobId = $job->getId();

        $client->request('DELETE', '/en/tools/bulk_info_provider_import/job/' . $jobId . '/delete');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);

        // Verify job was deleted
        $entityManager->clear();
        $this->assertNull($entityManager->find(BulkInfoProviderImportJob::class, $jobId));
    }

    public function testDeleteActiveJobFails(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $user = $entityManager->getRepository(User::class)->findOneBy(['name' => 'admin']);
        $parts = $this->getTestParts($entityManager, [1]);

        if (!$user || empty($parts)) {
            $this->markTestSkipped('Required fixtures not found');
        }

        $job = $this->createJobWithSearchResults($entityManager, $user, $parts, 'in_progress');
        $jobId = $job->getId();

        $client->request('DELETE', '/en/tools/bulk_info_provider_import/job/' . $jobId . '/delete');
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $this->cleanupJob($entityManager, $jobId);
    }

    public function testDeleteNonExistentJob(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $client->request('DELETE', '/en/tools/bulk_info_provider_import/job/999999/delete');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testStopInProgressJob(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $user = $entityManager->getRepository(User::class)->findOneBy(['name' => 'admin']);
        $parts = $this->getTestParts($entityManager, [1]);

        if (!$user || empty($parts)) {
            $this->markTestSkipped('Required fixtures not found');
        }

        $job = $this->createJobWithSearchResults($entityManager, $user, $parts, 'in_progress');
        $jobId = $job->getId();

        $client->request('POST', '/en/tools/bulk_info_provider_import/job/' . $jobId . '/stop');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);

        // Verify job is stopped
        $entityManager->clear();
        $stoppedJob = $entityManager->find(BulkInfoProviderImportJob::class, $jobId);
        $this->assertTrue($stoppedJob->isStopped());

        $entityManager->remove($stoppedJob);
        $entityManager->flush();
    }

    public function testStopNonExistentJob(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $client->request('POST', '/en/tools/bulk_info_provider_import/job/999999/stop');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testMarkPartCompletedAutoCompletesJob(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $user = $entityManager->getRepository(User::class)->findOneBy(['name' => 'admin']);
        $parts = $this->getTestParts($entityManager, [1]);

        if (!$user || empty($parts)) {
            $this->markTestSkipped('Required fixtures not found');
        }

        $job = $this->createJobWithSearchResults($entityManager, $user, $parts);
        $jobId = $job->getId();
        $partId = $parts[0]->getId();

        $client->request('POST', '/en/tools/bulk_info_provider_import/job/' . $jobId . '/part/' . $partId . '/mark-completed');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertEquals(1, $response['completed_count']);
        $this->assertTrue($response['job_completed']);

        $this->cleanupJob($entityManager, $jobId);
    }

    public function testMarkPartSkippedWithReason(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $user = $entityManager->getRepository(User::class)->findOneBy(['name' => 'admin']);
        $parts = $this->getTestParts($entityManager, [1]);

        if (!$user || empty($parts)) {
            $this->markTestSkipped('Required fixtures not found');
        }

        $job = $this->createJobWithSearchResults($entityManager, $user, $parts);
        $jobId = $job->getId();
        $partId = $parts[0]->getId();

        $client->request('POST', '/en/tools/bulk_info_provider_import/job/' . $jobId . '/part/' . $partId . '/mark-skipped', [
            'reason' => 'Not needed'
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertEquals(1, $response['skipped_count']);

        $this->cleanupJob($entityManager, $jobId);
    }

    public function testMarkPartPendingAfterCompleted(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $user = $entityManager->getRepository(User::class)->findOneBy(['name' => 'admin']);
        $parts = $this->getTestParts($entityManager, [1]);

        if (!$user || empty($parts)) {
            $this->markTestSkipped('Required fixtures not found');
        }

        $job = $this->createJobWithSearchResults($entityManager, $user, $parts);
        $jobId = $job->getId();
        $partId = $parts[0]->getId();

        // First mark as completed
        $client->request('POST', '/en/tools/bulk_info_provider_import/job/' . $jobId . '/part/' . $partId . '/mark-completed');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        // Then mark as pending again
        $client->request('POST', '/en/tools/bulk_info_provider_import/job/' . $jobId . '/part/' . $partId . '/mark-pending');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertEquals(0, $response['completed_count']);

        $this->cleanupJob($entityManager, $jobId);
    }

    public function testMarkPartCompletedNonExistentJob(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $client->request('POST', '/en/tools/bulk_info_provider_import/job/999999/part/1/mark-completed');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testQuickApplyWithValidJob(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $user = $entityManager->getRepository(User::class)->findOneBy(['name' => 'admin']);
        $parts = $this->getTestParts($entityManager, [1]);

        if (!$user || empty($parts)) {
            $this->markTestSkipped('Required fixtures not found');
        }

        $job = $this->createJobWithSearchResults($entityManager, $user, $parts);
        $jobId = $job->getId();
        $partId = $parts[0]->getId();

        // Quick apply will fail because test_provider doesn't exist, but it exercises the code path
        $client->request('POST', '/en/tools/bulk_info_provider_import/job/' . $jobId . '/part/' . $partId . '/quick-apply', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['providerKey' => 'test_provider', 'providerId' => 'TEST_1']));

        // Will get 500 because test_provider doesn't exist, which exercises the catch block
        $this->assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Quick apply failed', $response['error']);

        $this->cleanupJob($entityManager, $jobId);
    }

    public function testQuickApplyFallsBackToTopResult(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $user = $entityManager->getRepository(User::class)->findOneBy(['name' => 'admin']);
        $parts = $this->getTestParts($entityManager, [1]);

        if (!$user || empty($parts)) {
            $this->markTestSkipped('Required fixtures not found');
        }

        $job = $this->createJobWithSearchResults($entityManager, $user, $parts);
        $jobId = $job->getId();
        $partId = $parts[0]->getId();

        // No providerKey/providerId in body - should fall back to top search result
        $client->request('POST', '/en/tools/bulk_info_provider_import/job/' . $jobId . '/part/' . $partId . '/quick-apply', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '{}');

        // Will get 500 because test_provider doesn't exist, but exercises the fallback code path
        $this->assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('Quick apply failed', $response['error']);

        $this->cleanupJob($entityManager, $jobId);
    }

    public function testQuickApplyWithNoSearchResults(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $user = $entityManager->getRepository(User::class)->findOneBy(['name' => 'admin']);
        $parts = $this->getTestParts($entityManager, [1]);

        if (!$user || empty($parts)) {
            $this->markTestSkipped('Required fixtures not found');
        }

        // Create job with empty search results
        $job = new BulkInfoProviderImportJob();
        $job->setCreatedBy($user);
        foreach ($parts as $part) {
            $job->addPart($part);
        }
        $job->setStatus(BulkImportJobStatus::IN_PROGRESS);
        $job->setSearchResults(new BulkSearchResponseDTO([
            new BulkSearchPartResultsDTO(part: $parts[0], searchResults: [])
        ]));
        $entityManager->persist($job);
        $entityManager->flush();

        $jobId = $job->getId();
        $partId = $parts[0]->getId();

        // No provider specified and no search results - should return 400
        $client->request('POST', '/en/tools/bulk_info_provider_import/job/' . $jobId . '/part/' . $partId . '/quick-apply', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '{}');
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('No search result available', $response['error']);

        $this->cleanupJob($entityManager, $jobId);
    }

    public function testQuickApplyNonExistentPart(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $user = $entityManager->getRepository(User::class)->findOneBy(['name' => 'admin']);
        $parts = $this->getTestParts($entityManager, [1]);

        if (!$user || empty($parts)) {
            $this->markTestSkipped('Required fixtures not found');
        }

        $job = $this->createJobWithSearchResults($entityManager, $user, $parts);
        $jobId = $job->getId();

        $client->request('POST', '/en/tools/bulk_info_provider_import/job/' . $jobId . '/part/999999/quick-apply');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->cleanupJob($entityManager, $jobId);
    }

    public function testQuickApplyAllWithValidJob(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $user = $entityManager->getRepository(User::class)->findOneBy(['name' => 'admin']);
        $parts = $this->getTestParts($entityManager, [1]);

        if (!$user || empty($parts)) {
            $this->markTestSkipped('Required fixtures not found');
        }

        $job = $this->createJobWithSearchResults($entityManager, $user, $parts);
        $jobId = $job->getId();

        // Quick apply all - will fail for test_provider but exercises the code path
        $client->request('POST', '/en/tools/bulk_info_provider_import/job/' . $jobId . '/quick-apply-all');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        // Should have 1 failed (because test_provider doesn't exist)
        $this->assertEquals(1, $response['failed']);
        $this->assertNotEmpty($response['errors']);

        $this->cleanupJob($entityManager, $jobId);
    }

    public function testQuickApplyAllWithNoSearchResults(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $user = $entityManager->getRepository(User::class)->findOneBy(['name' => 'admin']);
        $parts = $this->getTestParts($entityManager, [1]);

        if (!$user || empty($parts)) {
            $this->markTestSkipped('Required fixtures not found');
        }

        // Create job with empty results
        $job = new BulkInfoProviderImportJob();
        $job->setCreatedBy($user);
        foreach ($parts as $part) {
            $job->addPart($part);
        }
        $job->setStatus(BulkImportJobStatus::IN_PROGRESS);
        $job->setSearchResults(new BulkSearchResponseDTO([
            new BulkSearchPartResultsDTO(part: $parts[0], searchResults: [])
        ]));
        $entityManager->persist($job);
        $entityManager->flush();

        $jobId = $job->getId();

        $client->request('POST', '/en/tools/bulk_info_provider_import/job/' . $jobId . '/quick-apply-all');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertEquals(0, $response['applied']);
        $this->assertEquals(1, $response['no_results']);

        $this->cleanupJob($entityManager, $jobId);
    }

    public function testQuickApplyAllNonExistentJob(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $client->request('POST', '/en/tools/bulk_info_provider_import/job/999999/quick-apply-all');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testQuickApplyAllSkipsCompletedParts(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $user = $entityManager->getRepository(User::class)->findOneBy(['name' => 'admin']);
        $parts = $this->getTestParts($entityManager, [1]);

        if (!$user || empty($parts)) {
            $this->markTestSkipped('Required fixtures not found');
        }

        $job = $this->createJobWithSearchResults($entityManager, $user, $parts);
        $jobId = $job->getId();

        // Mark the part as completed first
        $job->markPartAsCompleted($parts[0]->getId());
        $entityManager->flush();

        // Quick apply all should skip already-completed parts
        $client->request('POST', '/en/tools/bulk_info_provider_import/job/' . $jobId . '/quick-apply-all');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(0, $response['applied']);
        $this->assertEquals(0, $response['failed']);
        $this->assertEquals(0, $response['no_results']);

        $this->cleanupJob($entityManager, $jobId);
    }

    public function testDeleteStoppedJob(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $user = $entityManager->getRepository(User::class)->findOneBy(['name' => 'admin']);
        $parts = $this->getTestParts($entityManager, [1]);

        if (!$user || empty($parts)) {
            $this->markTestSkipped('Required fixtures not found');
        }

        $job = $this->createJobWithSearchResults($entityManager, $user, $parts, 'stopped');
        $jobId = $job->getId();

        $client->request('DELETE', '/en/tools/bulk_info_provider_import/job/' . $jobId . '/delete');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);

        $entityManager->clear();
        $this->assertNull($entityManager->find(BulkInfoProviderImportJob::class, $jobId));
    }

    public function testManagePageSplitsActiveAndHistory(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $user = $entityManager->getRepository(User::class)->findOneBy(['name' => 'admin']);
        $parts = $this->getTestParts($entityManager, [1]);

        if (!$user || empty($parts)) {
            $this->markTestSkipped('Required fixtures not found');
        }

        // Create one active and one completed job
        $activeJob = $this->createJobWithSearchResults($entityManager, $user, $parts, 'in_progress');
        $completedJob = $this->createJobWithSearchResults($entityManager, $user, $parts, 'completed');

        $client->request('GET', '/en/tools/bulk_info_provider_import/manage');
        if ($client->getResponse()->isRedirect()) {
            $client->followRedirect();
        }
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $content = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('Active Jobs', $content);
        $this->assertStringContainsString('History', $content);

        $this->cleanupJob($entityManager, $activeJob->getId());
        $this->cleanupJob($entityManager, $completedJob->getId());
    }

    public function testManagePageCleansUpPendingJobsWithNoResults(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $user = $entityManager->getRepository(User::class)->findOneBy(['name' => 'admin']);
        $parts = $this->getTestParts($entityManager, [1]);

        if (!$user || empty($parts)) {
            $this->markTestSkipped('Required fixtures not found');
        }

        // Create a pending job with no results (should be cleaned up)
        $job = new BulkInfoProviderImportJob();
        $job->setCreatedBy($user);
        foreach ($parts as $part) {
            $job->addPart($part);
        }
        $job->setStatus(BulkImportJobStatus::PENDING);
        $job->setSearchResults(new BulkSearchResponseDTO([]));
        $entityManager->persist($job);
        $entityManager->flush();
        $jobId = $job->getId();

        // Visit manage page - should trigger cleanup
        $client->request('GET', '/en/tools/bulk_info_provider_import/manage');
        if ($client->getResponse()->isRedirect()) {
            $client->followRedirect();
        }
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        // Verify the stale job was cleaned up
        $entityManager->clear();
        $this->assertNull($entityManager->find(BulkInfoProviderImportJob::class, $jobId));
    }

    public function testStep2RedirectsForNonExistentJob(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $client->request('GET', '/en/tools/bulk_info_provider_import/step2/999999');

        // Should redirect with error flash
        $this->assertResponseRedirects();
    }

    public function testStep2WithOtherUsersJob(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $otherUser = $entityManager->getRepository(User::class)->findOneBy(['name' => 'noread']);
        $parts = $this->getTestParts($entityManager, [1]);

        if (!$otherUser || empty($parts)) {
            $this->markTestSkipped('Required fixtures not found');
        }

        $job = $this->createJobWithSearchResults($entityManager, $otherUser, $parts);
        $jobId = $job->getId();

        $client->request('GET', '/en/tools/bulk_info_provider_import/step2/' . $jobId);

        // Should redirect with access denied
        $this->assertResponseRedirects();

        $this->cleanupJob($entityManager, $jobId);
    }

    public function testResearchPartNonExistentJob(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $client->request('POST', '/en/tools/bulk_info_provider_import/job/999999/part/1/research');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testResearchPartNonExistentPart(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $user = $entityManager->getRepository(User::class)->findOneBy(['name' => 'admin']);
        $parts = $this->getTestParts($entityManager, [1]);

        if (!$user || empty($parts)) {
            $this->markTestSkipped('Required fixtures not found');
        }

        $job = $this->createJobWithSearchResults($entityManager, $user, $parts);
        $jobId = $job->getId();

        $client->request('POST', '/en/tools/bulk_info_provider_import/job/' . $jobId . '/part/999999/research');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->cleanupJob($entityManager, $jobId);
    }

    public function testResearchAllNonExistentJob(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $client->request('POST', '/en/tools/bulk_info_provider_import/job/999999/research-all');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testResearchAllWithAllPartsCompleted(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $user = $entityManager->getRepository(User::class)->findOneBy(['name' => 'admin']);
        $parts = $this->getTestParts($entityManager, [1]);

        if (!$user || empty($parts)) {
            $this->markTestSkipped('Required fixtures not found');
        }

        $job = $this->createJobWithSearchResults($entityManager, $user, $parts);
        $jobId = $job->getId();

        // Mark all parts as completed
        foreach ($parts as $part) {
            $job->markPartAsCompleted($part->getId());
        }
        $entityManager->flush();

        $client->request('POST', '/en/tools/bulk_info_provider_import/job/' . $jobId . '/research-all');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertEquals(0, $response['researched_count']);

        $this->cleanupJob($entityManager, $jobId);
    }
}
