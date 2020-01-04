<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Tests\EventSubscriber;

use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\U2FKey;
use App\Entity\UserSystem\User;
use App\EventSubscriber\PasswordChangeNeededSubscriber;
use PHPUnit\Framework\TestCase;

class PasswordChangeNeededSubscriberTest extends TestCase
{
    public function testTFARedirectNeeded()
    {
        $user = new User();
        $group = new Group();

        //A user without a group must not redirect
        $user->setGroup(null);
        $this->assertFalse(PasswordChangeNeededSubscriber::TFARedirectNeeded($user));

        //When the group does not enforce the redirect the user must not be redirected
        $user->setGroup($group);
        $this->assertFalse(PasswordChangeNeededSubscriber::TFARedirectNeeded($user));

        //The user must be redirected if the group enforces 2FA and it does not have a method
        $group->setEnforce2FA(true);
        $this->assertTrue(PasswordChangeNeededSubscriber::TFARedirectNeeded($user));

        //User must not be redirect if google authenticator is setup
        $user->setGoogleAuthenticatorSecret('abcd');
        $this->assertFalse(PasswordChangeNeededSubscriber::TFARedirectNeeded($user));

        //User must not be redirect if 2FA is setup
        $user->setGoogleAuthenticatorSecret(null);
        $user->addU2FKey(new U2FKey());
        $this->assertFalse(PasswordChangeNeededSubscriber::TFARedirectNeeded($user));
    }
}
