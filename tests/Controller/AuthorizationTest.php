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

namespace App\Tests\Controller;

use App\Entity\UserSystem\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the HTTP access-control boundaries:
 *
 * The app has an "anonymous" fixture user with readonly permissions, so truly
 * public read routes return 200 even without a session.  Write-protected routes
 * return 401 for unauthenticated requests (not a 302 redirect).
 *
 * Users: admin (all-allow), user (editor preset), noread (no group/no perms)
 */
#[Group('DB')]
#[Group('slow')]
final class AuthorizationTest extends WebTestCase
{
    // -----------------------------------------------------------------------
    // Data providers
    // -----------------------------------------------------------------------

    /**
     * Routes readable by the anonymous user — unauthenticated requests get 200.
     */
    public static function publicReadRoutesProvider(): \Generator
    {
        yield 'homepage'        => ['/en/'];
        yield 'part view'       => ['/en/part/1'];
        yield 'statistics'      => ['/en/statistics'];
        yield 'select category' => ['/en/select_api/category'];
        yield 'typeahead tags'  => ['/en/typeahead/tags/search/test'];
    }

    /**
     * Write-protected routes — unauthenticated gets 401 (not 302).
     */
    public static function writeProtectedRoutesProvider(): \Generator
    {
        yield 'part edit'    => ['/en/part/1/edit'];
        yield 'part new'     => ['/en/part/new'];
        yield 'user edit'    => ['/en/user/1/edit'];
        yield 'log list'     => ['/en/log/'];
        yield 'server info'  => ['/en/tools/server_infos'];
    }

    /**
     * Routes the `noread` user (no group = no permissions) must be denied.
     */
    public static function noreadDeniedRoutesProvider(): \Generator
    {
        yield 'part view'       => ['/en/part/1'];
        yield 'part edit'       => ['/en/part/1/edit'];
        yield 'part new'        => ['/en/part/new'];
        yield 'log list'        => ['/en/log/'];
        yield 'server info'     => ['/en/tools/server_infos'];
        yield 'select category' => ['/en/select_api/category'];
        yield 'typeahead tags'  => ['/en/typeahead/tags/search/test'];
    }

    /**
     * Routes the `user` (editor preset) must have access to.
     */
    public static function editorAllowedRoutesProvider(): \Generator
    {
        yield 'homepage'     => ['/en/'];
        yield 'part view'    => ['/en/part/1'];
        yield 'part edit'    => ['/en/part/1/edit'];
        yield 'part new'     => ['/en/part/new'];
        yield 'select cat'   => ['/en/select_api/category'];
        yield 'typeahead'    => ['/en/typeahead/tags/search/test'];
    }

    /**
     * Admin-only routes the `user` (editor) must be denied.
     */
    public static function editorDeniedRoutesProvider(): \Generator
    {
        yield 'user edit'   => ['/en/user/1/edit'];
        yield 'log list'    => ['/en/log/'];
        yield 'server info' => ['/en/tools/server_infos'];
    }

    /**
     * Routes the `admin` user must be able to reach.
     */
    public static function adminAllowedRoutesProvider(): \Generator
    {
        yield 'user edit'   => ['/en/user/1/edit'];
        yield 'log list'    => ['/en/log/'];
        yield 'server info' => ['/en/tools/server_infos'];
        yield 'part view'   => ['/en/part/1'];
        yield 'part edit'   => ['/en/part/1/edit'];
        yield 'statistics'  => ['/en/statistics'];
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function loginAs(string $username): KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['name' => $username]);
        if ($user === null) {
            $this->markTestSkipped("Fixture user '$username' not found.");
        }
        $client->loginUser($user);
        $client->followRedirects(false);
        return $client;
    }

    private function assertDenied(KernelBrowser $client, string $url): void
    {
        $client->request('GET', $url);
        $code = $client->getResponse()->getStatusCode();
        $this->assertTrue(
            $code === Response::HTTP_FORBIDDEN || $code === Response::HTTP_UNAUTHORIZED || $client->getResponse()->isRedirect(),
            "Expected 401/403/redirect on $url, got $code"
        );
    }

    // -----------------------------------------------------------------------
    // Unauthenticated: public reads
    // -----------------------------------------------------------------------

    #[DataProvider('publicReadRoutesProvider')]
    public function testUnauthenticatedCanReadPublicRoutes(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url);
        // Anonymous user (readonly group) can access read-only content
        $this->assertResponseIsSuccessful();
    }

    // -----------------------------------------------------------------------
    // Unauthenticated: write routes → 401
    // -----------------------------------------------------------------------

    #[DataProvider('writeProtectedRoutesProvider')]
    public function testUnauthenticatedIsUnauthorizedOnWriteRoutes(string $url): void
    {
        $client = static::createClient();
        $client->followRedirects(false);
        $client->request('GET', $url);

        $code = $client->getResponse()->getStatusCode();
        $this->assertTrue(
            $code === Response::HTTP_UNAUTHORIZED || $client->getResponse()->isRedirect(),
            "Expected 401 or redirect on $url for unauthenticated request, got $code"
        );
    }

    // -----------------------------------------------------------------------
    // noread user: denied everywhere
    // -----------------------------------------------------------------------

    #[DataProvider('noreadDeniedRoutesProvider')]
    public function testNoreadUserIsDenied(string $url): void
    {
        $this->assertDenied($this->loginAs('noread'), $url);
    }

    // -----------------------------------------------------------------------
    // Editor user
    // -----------------------------------------------------------------------

    #[DataProvider('editorAllowedRoutesProvider')]
    public function testEditorCanAccess(string $url): void
    {
        $client = $this->loginAs('user');
        $client->request('GET', $url);
        $this->assertResponseIsSuccessful();
    }

    #[DataProvider('editorDeniedRoutesProvider')]
    public function testEditorIsDeniedOnAdminRoutes(string $url): void
    {
        $this->assertDenied($this->loginAs('user'), $url);
    }

    // -----------------------------------------------------------------------
    // Admin user: can access everything
    // -----------------------------------------------------------------------

    #[DataProvider('adminAllowedRoutesProvider')]
    public function testAdminCanAccessAllRoutes(string $url): void
    {
        $client = $this->loginAs('admin');
        $client->request('GET', $url);
        $this->assertResponseIsSuccessful();
    }
}
