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

namespace App\Tests\Security;

use App\Entity\UserSystem\User;
use App\Security\SamlUserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SamlUserFactoryTest extends WebTestCase
{

    /** @var SamlUserFactory */
    protected $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(SamlUserFactory::class);
    }

    public function testCreateUser(): void
    {
        $user = $this->service->createUser('sso_user', [
            'email' => ['j.doe@invalid.invalid'],
            'urn:oid:2.5.4.42' => ['John'],
            'urn:oid:2.5.4.4' => ['Doe'],
            'department' => ['IT']
        ]);

        $this->assertInstanceOf(User::class, $user);

        $this->assertEquals('sso_user', $user->getUsername());
        //User must not change his password
        $this->assertFalse($user->isNeedPwChange());
        //And must not be disabled
        $this->assertFalse($user->isDisabled());
        //Password should not be set
        $this->assertSame('!!SAML!!', $user->getPassword());

        //Info should be set
        $this->assertEquals('John', $user->getFirstName());
        $this->assertEquals('Doe', $user->getLastName());
        $this->assertEquals('IT', $user->getDepartment());
        $this->assertEquals('j.doe@invalid.invalid', $user->getEmail());
    }

    public function testMapSAMLRolesToLocalGroupID(): void
    {
        $mapping = [
            'admin' => 2, //This comes first, as this should have higher priority
            'employee' => 1,
            'manager' => 3,
            'administrator' => 2,
            '*' => 4,
        ];

        //Test if mapping works
        $this->assertSame(1, $this->service->mapSAMLRolesToLocalGroupID(['employee'], $mapping));
        //Only the first valid mapping should be used
        $this->assertSame(2, $this->service->mapSAMLRolesToLocalGroupID(['employee', 'admin'], $mapping));
        $this->assertSame(2, $this->service->mapSAMLRolesToLocalGroupID(['does_not_matter', 'admin', 'employee'], $mapping));
        $this->assertSame(1, $this->service->mapSAMLRolesToLocalGroupID(['employee', 'does_not_matter', 'manager'], $mapping));
        $this->assertSame(3, $this->service->mapSAMLRolesToLocalGroupID(['administrator', 'does_not_matter', 'manager'], $mapping));
        //Test if mapping is case-sensitive
        $this->assertEquals(4, $this->service->mapSAMLRolesToLocalGroupID(['ADMIN'], $mapping));

        //Test that wildcard mapping works
        $this->assertEquals(4, $this->service->mapSAMLRolesToLocalGroupID(['entry1', 'entry2'], $mapping));
        $this->assertEquals(4, $this->service->mapSAMLRolesToLocalGroupID([], $mapping));
    }
}
