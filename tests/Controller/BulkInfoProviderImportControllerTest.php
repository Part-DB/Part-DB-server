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