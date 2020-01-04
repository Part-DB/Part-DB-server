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

namespace App\Tests\Entity\UserSystem;

use App\Entity\UserSystem\U2FKey;
use App\Entity\UserSystem\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testGetFullName()
    {
        $user = new User();
        $user->setName('username');
        $user->setFirstName('John');
        $user->setLastName('Doe');

        $this->assertEquals('John Doe', $user->getFullName(false));
        $this->assertEquals('John Doe (username)', $user->getFullName(true));
    }

    public function googleAuthenticatorEnabledDataProvider(): array
    {
        return [
            [null, false],
            ['', false],
            ['SSSk38498', true],
        ];
    }

    /**
     * @dataProvider googleAuthenticatorEnabledDataProvider
     */
    public function testIsGoogleAuthenticatorEnabled(?string $secret, bool $expected)
    {
        $user = new User();
        $user->setGoogleAuthenticatorSecret($secret);
        $this->assertSame($expected, $user->isGoogleAuthenticatorEnabled());
    }

    /**
     * @requires PHPUnit 8
     */
    public function testSetBackupCodes()
    {
        $user = new User();
        $codes = ['test', 'invalid', 'test'];
        $user->setBackupCodes($codes);
        // Backup Codes generation date must be changed!
        $this->assertEqualsWithDelta(new \DateTime(), $user->getBackupCodesGenerationDate(), 0.1);
        $this->assertEquals($codes, $user->getBackupCodes());

        //Test what happens if we delete the backup keys
        $user->setBackupCodes([]);
        $this->assertEmpty($user->getBackupCodes());
        $this->assertNull($user->getBackupCodesGenerationDate());
    }

    public function testIsBackupCode()
    {
        $user = new User();
        $codes = ['aaaa', 'bbbb', 'cccc', 'dddd'];
        $user->setBackupCodes($codes);

        $this->assertTrue($user->isBackupCode('aaaa'));
        $this->assertTrue($user->isBackupCode('cccc'));

        $this->assertFalse($user->isBackupCode(''));
        $this->assertFalse($user->isBackupCode('zzzz'));
    }

    public function testInvalidateBackupCode()
    {
        $user = new User();
        $codes = ['aaaa', 'bbbb', 'cccc', 'dddd'];
        $user->setBackupCodes($codes);

        //Ensure the code is valid
        $this->assertTrue($user->isBackupCode('aaaa'));
        $this->assertTrue($user->isBackupCode('bbbb'));
        //Invalidate code, afterwards the code has to be invalid!
        $user->invalidateBackupCode('bbbb');
        $this->assertFalse($user->isBackupCode('bbbb'));
        $this->assertTrue($user->isBackupCode('aaaa'));

        //No exception must happen, when we try to invalidate an not existing backup key!
        $user->invalidateBackupCode('zzzz');
    }

    public function testInvalidateTrustedDeviceTokens()
    {
        $user = new User();
        $old_value = $user->getTrustedTokenVersion();
        //To invalidate the token, the new value must be bigger than the old value
        $user->invalidateTrustedDeviceTokens();
        $this->assertGreaterThan($old_value, $user->getTrustedTokenVersion());
    }

    public function testIsU2fEnabled()
    {
        $user = new User();
        $user->addU2FKey(new U2FKey());
        $this->assertTrue($user->isU2FAuthEnabled());

        $user->getU2FKeys()->clear();
        $this->assertFalse($user->isU2FAuthEnabled());
    }
}
