<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\User;
use App\Entity\UserSystem\WebauthnKey;
use App\EventSubscriber\UserSystem\PasswordChangeNeededSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Webauthn\TrustPath\EmptyTrustPath;

class PasswordChangeNeededSubscriberTest extends TestCase
{
    public function testTFARedirectNeeded(): void
    {
        $user = new User();
        $group = new Group();

        //A user without a group must not redirect
        $user->setGroup(null);
        $this->assertFalse(PasswordChangeNeededSubscriber::TFARedirectNeeded($user));

        //When the group does not enforce the redirect the user must not be redirected
        $user->setGroup($group);
        $this->assertFalse(PasswordChangeNeededSubscriber::TFARedirectNeeded($user));

        //The user must be redirected if the group enforces 2FA, and it does not have a method
        $group->setEnforce2FA(true);
        $this->assertTrue(PasswordChangeNeededSubscriber::TFARedirectNeeded($user));

        //User must not be redirect if google authenticator is set up
        $user->setGoogleAuthenticatorSecret('abcd');
        $this->assertFalse(PasswordChangeNeededSubscriber::TFARedirectNeeded($user));

        //User must not be redirect if 2FA is set up
        $user->setGoogleAuthenticatorSecret(null);
        $user->addWebauthnKey(new WebauthnKey(
            "Test",
            "Test",
            [],
            "Test",
            new EmptyTrustPath(),
           Uuid::v4(),
            "",
            "",
            0
        ));
        $this->assertFalse(PasswordChangeNeededSubscriber::TFARedirectNeeded($user));
    }
}
