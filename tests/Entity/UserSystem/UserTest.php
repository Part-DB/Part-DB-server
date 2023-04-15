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

namespace App\Tests\Entity\UserSystem;

use App\Entity\UserSystem\User;
use App\Entity\UserSystem\WebauthnKey;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Webauthn\TrustPath\EmptyTrustPath;

class UserTest extends TestCase
{
    public function testGetFullName(): void
    {
        $user = new User();
        $user->setName('username');
        $user->setFirstName('John');
        $user->setLastName('Doe');

        $this->assertSame('John Doe', $user->getFullName(false));
        $this->assertSame('John Doe (@username)', $user->getFullName(true));

        $user->setLastName('');
        $this->assertSame('John (@username)', $user->getFullName(true));
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
    public function testIsGoogleAuthenticatorEnabled(?string $secret, bool $expected): void
    {
        $user = new User();
        $user->setGoogleAuthenticatorSecret($secret);
        $this->assertSame($expected, $user->isGoogleAuthenticatorEnabled());
    }

    /**
     * @requires PHPUnit 8
     */
    public function testSetBackupCodes(): void
    {
        $user = new User();
        $this->assertNull($user->getBackupCodesGenerationDate());

        $codes = ['test', 'invalid', 'test'];
        $user->setBackupCodes($codes);
        // Backup Codes generation date must be changed!
        $this->assertInstanceOf(\DateTime::class, $user->getBackupCodesGenerationDate());
        $this->assertSame($codes, $user->getBackupCodes());

        //Test what happens if we delete the backup keys
        $user->setBackupCodes([]);
        $this->assertEmpty($user->getBackupCodes());
        $this->assertNull($user->getBackupCodesGenerationDate());
    }

    public function testIsBackupCode(): void
    {
        $user = new User();
        $codes = ['aaaa', 'bbbb', 'cccc', 'dddd'];
        $user->setBackupCodes($codes);

        $this->assertTrue($user->isBackupCode('aaaa'));
        $this->assertTrue($user->isBackupCode('cccc'));

        $this->assertFalse($user->isBackupCode(''));
        $this->assertFalse($user->isBackupCode('zzzz'));
    }

    public function testInvalidateBackupCode(): void
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

    public function testInvalidateTrustedDeviceTokens(): void
    {
        $user = new User();
        $old_value = $user->getTrustedTokenVersion();
        //To invalidate the token, the new value must be bigger than the old value
        $user->invalidateTrustedDeviceTokens();
        $this->assertGreaterThan($old_value, $user->getTrustedTokenVersion());
    }

    public function testIsWebauthnEnabled(): void
    {
        $user = new User();
        $user->addWebauthnKey(new WebauthnKey(
            "Test",
            "Test",
            [],
            "Test",
            new EmptyTrustPath(),
            Uuid::fromDateTime(new \DateTime()),
            "",
            "",
            0
        ));
        $this->assertTrue($user->isWebAuthnAuthenticatorEnabled());

        $result = $user->getWebauthnKeys();
        if($result instanceof Collection){
            $result->clear();
        }
        $this->assertFalse($user->isWebAuthnAuthenticatorEnabled());
    }

    public function testSetSAMLAttributes(): void
    {
        $data = [
            'firstName' => ['John'],
            'lastName' => ['Doe'],
            'email' => ['j.doe@invalid.invalid'],
            'department' => ['Test Department'],
        ];

        $user = new User();
        $user->setSAMLAttributes($data);

        //Test if the data was set correctly
        $this->assertSame('John', $user->getFirstName());
        $this->assertSame('Doe', $user->getLastName());
        $this->assertSame('j.doe@invalid.invalid', $user->getEmail());
        $this->assertSame('Test Department', $user->getDepartment());

        //Test that it works for X500 attributes
        $data = [
            'urn:oid:2.5.4.42' => ['Jane'],
            'urn:oid:2.5.4.4' => ['Dane'],
            'urn:oid:1.2.840.113549.1.9.1' => ['mail@invalid.invalid'],
            ];

        $user->setSAMLAttributes($data);

        //Data must be changed
        $this->assertSame('Jane', $user->getFirstName());
        $this->assertSame('Dane', $user->getLastName());
        $this->assertSame('mail@invalid.invalid', $user->getEmail());

        //Department must not be changed
        $this->assertSame('Test Department', $user->getDepartment());
    }
}
