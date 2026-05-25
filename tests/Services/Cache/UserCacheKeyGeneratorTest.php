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

namespace App\Tests\Services\Cache;

use App\Entity\UserSystem\User;
use App\Services\Cache\UserCacheKeyGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class UserCacheKeyGeneratorTest extends TestCase
{
    private function makeGenerator(?User $loggedInUser, ?Request $request = null): UserCacheKeyGenerator
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($loggedInUser);

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);

        return new UserCacheKeyGenerator($security, $requestStack);
    }

    private function makeUserWithId(int $id): User
    {
        $user = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, $id);
        return $user;
    }

    public function testAnonymousUserKeyContainsAnonymousId(): void
    {
        $service = $this->makeGenerator(null);
        $key = $service->generateKey();
        $this->assertStringContainsString((string) User::ID_ANONYMOUS, $key);
    }

    public function testExplicitAnonymousUserGivesSameKeyAsNull(): void
    {
        $anonUser = $this->makeUserWithId(User::ID_ANONYMOUS);
        $anonUser->setName('anonymous');

        $service = $this->makeGenerator(null);
        $keyFromNull = $service->generateKey(null);
        $keyFromAnon = $service->generateKey($anonUser);
        $this->assertSame($keyFromNull, $keyFromAnon);
    }

    public function testKeyForRealUserContainsUserId(): void
    {
        $user = $this->makeUserWithId(42);
        $service = $this->makeGenerator(null);

        $key = $service->generateKey($user);
        $this->assertStringContainsString('42', $key);
        $this->assertStringNotContainsString((string) User::ID_ANONYMOUS, $key);
    }

    public function testLocaleFromRequestIsIncludedInKey(): void
    {
        $request = Request::create('/');
        $request->setLocale('de');

        $service = $this->makeGenerator(null, $request);
        $key = $service->generateKey();
        $this->assertStringContainsString('de', $key);
    }

    public function testDifferentUsersProduceDifferentKeys(): void
    {
        $service = $this->makeGenerator(null);

        $user1 = $this->makeUserWithId(10);
        $user2 = $this->makeUserWithId(20);

        $this->assertNotSame($service->generateKey($user1), $service->generateKey($user2));
    }

    public function testCurrentlyLoggedInUserIsUsedWhenNoExplicitUser(): void
    {
        $loggedIn = $this->makeUserWithId(99);
        $service = $this->makeGenerator($loggedIn);

        $key = $service->generateKey();
        $this->assertStringContainsString('99', $key);
    }
}
