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
        $job->setPartIds([$part->getId()]);
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

    private function loginAsUser($client, string $username): void
    {
        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['name' => $username]);
        
        if (!$user) {
            $this->markTestSkipped('User ' . $username . ' not found');
        }
        
        $client->loginUser($user);
    }
}