<?php

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
