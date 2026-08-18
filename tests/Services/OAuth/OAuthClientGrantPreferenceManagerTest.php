<?php
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

declare(strict_types=1);

namespace App\Tests\Services\OAuth;

use App\Entity\UserSystem\ApiTokenLevel;
use App\Entity\UserSystem\OAuthClientGrantPreference;
use App\Services\OAuth\OAuthClientGrantPreferenceManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class OAuthClientGrantPreferenceManagerTest extends KernelTestCase
{
    public function testSaveUpsertsAllFields(): void
    {
        self::bootKernel();
        $manager = static::getContainer()->get(OAuthClientGrantPreferenceManager::class);

        $userIdentifier = 'test-user-'.bin2hex(random_bytes(8));
        $clientIdentifier = 'test-client-'.bin2hex(random_bytes(8));

        self::assertNull($manager->find($userIdentifier, $clientIdentifier));

        $manager->save($userIdentifier, $clientIdentifier, ApiTokenLevel::EDIT, 'First Name', 7);
        $preference = $manager->find($userIdentifier, $clientIdentifier);
        self::assertInstanceOf(OAuthClientGrantPreference::class, $preference);
        self::assertSame(ApiTokenLevel::EDIT, $preference->getScopeLevel());
        self::assertSame('First Name', $preference->getFriendlyName());
        self::assertSame(7, $preference->getRefreshTokenTtlDays());
        self::assertNotNull($preference->getCreatedAt());

        // A second save() for the same (user, client) pair must update the existing row, not create
        // a second one.
        $manager->save($userIdentifier, $clientIdentifier, ApiTokenLevel::READ_ONLY, null, null);
        $updated = $manager->find($userIdentifier, $clientIdentifier);
        self::assertInstanceOf(OAuthClientGrantPreference::class, $updated);
        self::assertSame($preference->getId(), $updated->getId());
        self::assertSame(ApiTokenLevel::READ_ONLY, $updated->getScopeLevel());
        self::assertNull($updated->getFriendlyName());
        self::assertNull($updated->getRefreshTokenTtlDays());
        // createdAt must not change on a re-save of an existing preference.
        self::assertEquals($preference->getCreatedAt(), $updated->getCreatedAt());
    }

    public function testRecordUsageSetsLastUsedAndSelfHealsMissingPreference(): void
    {
        self::bootKernel();
        $manager = static::getContainer()->get(OAuthClientGrantPreferenceManager::class);

        $userIdentifier = 'test-user-'.bin2hex(random_bytes(8));
        $clientIdentifier = 'test-client-'.bin2hex(random_bytes(8));

        self::assertNull($manager->find($userIdentifier, $clientIdentifier));

        // No preference exists yet - recordUsage() must create one, inferring the scope level from the
        // token's own scopes rather than silently dropping the usage record.
        $manager->recordUsage($userIdentifier, $clientIdentifier, ['read_only', 'edit']);

        $preference = $manager->find($userIdentifier, $clientIdentifier);
        self::assertInstanceOf(OAuthClientGrantPreference::class, $preference);
        self::assertSame(ApiTokenLevel::EDIT, $preference->getScopeLevel());
        self::assertNotNull($preference->getLastUsedAt());
    }

    public function testRecordUsageDoesNotOverwriteExistingPreferenceFields(): void
    {
        self::bootKernel();
        $manager = static::getContainer()->get(OAuthClientGrantPreferenceManager::class);

        $userIdentifier = 'test-user-'.bin2hex(random_bytes(8));
        $clientIdentifier = 'test-client-'.bin2hex(random_bytes(8));

        $manager->save($userIdentifier, $clientIdentifier, ApiTokenLevel::ADMIN, 'My Device', 30);

        $manager->recordUsage($userIdentifier, $clientIdentifier, ['read_only']);

        $preference = $manager->find($userIdentifier, $clientIdentifier);
        self::assertInstanceOf(OAuthClientGrantPreference::class, $preference);
        // Untouched by recordUsage() - only lastUsedAt changes.
        self::assertSame(ApiTokenLevel::ADMIN, $preference->getScopeLevel());
        self::assertSame('My Device', $preference->getFriendlyName());
        self::assertSame(30, $preference->getRefreshTokenTtlDays());
        self::assertNotNull($preference->getLastUsedAt());
    }
}
