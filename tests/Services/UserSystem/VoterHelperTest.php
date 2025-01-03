<?php

declare(strict_types=1);

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
namespace App\Tests\Services\UserSystem;

use App\Entity\UserSystem\ApiToken;
use App\Entity\UserSystem\ApiTokenLevel;
use App\Entity\UserSystem\PermissionData;
use App\Entity\UserSystem\User;
use App\Security\ApiTokenAuthenticatedToken;
use App\Services\UserSystem\VoterHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

class VoterHelperTest extends KernelTestCase
{

    protected ?VoterHelper $service = null;

    protected ?User $user = null;

    protected function setUp(): void
    {
        //Get a service instance.
        self::bootKernel();
        $this->service = self::getContainer()->get(VoterHelper::class);

        //Set up a mocked user
        $user_perms = new PermissionData();
        $user_perms->setPermissionValue('parts', 'read', true) //read
        ->setPermissionValue('parts', 'edit', false) //edit
        ->setPermissionValue('parts', 'create', null) //create
        ->setPermissionValue('parts', 'move', null) //move
        ->setPermissionValue('parts', 'delete', null) //delete

        ->setPermissionValue('footprints', 'edit', true)
            ->setPermissionValue('footprints', 'create', false)
        ;

        $this->user = $this->createMock(User::class);
        $this->user->method('getPermissions')->willReturn($user_perms);
    }

    public function testResolveUserAnonUser(): void
    {
        //If the user is null, the anonymous user should be returned.

        $anonymousToken = new NullToken();
        $this->assertNull($anonymousToken->getUser());
        $user = $this->service->resolveUser($anonymousToken);
        //Ensure that this is the anonymous user.
        $this->assertNotNull($user);
        $this->assertTrue($user->isAnonymousUser());
    }

    public function testResolveUser(): void
    {
        //For a token with a user, the user should be returned.
        $token = new PostAuthenticationToken($this->user, 'main', ['ROLE_USER']);
        $this->assertSame($this->user, $token->getUser());
        $user = $this->service->resolveUser($token);
        $this->assertSame($this->user, $user);
    }

    public function testIsGrantedTrinaryNonAPI(): void
    {
        //For a UserNamePasswordToken everything should work as expected.
        $token = new UsernamePasswordToken($this->user, 'main');
        $this->assertTrue($this->service->isGrantedTrinary($token, 'parts', 'read'));
        $this->assertFalse($this->service->isGrantedTrinary($token, 'parts', 'edit'));
        $this->assertNull($this->service->isGrantedTrinary($token, 'parts', 'create'));
    }

    public function testIsGrantedTrinaryReadOnlyAPI(): void
    {
        //Create a API token
        $api_token = new ApiToken();
        $api_token->setLevel(ApiTokenLevel::READ_ONLY)->setName('Test Token');
        //Create an auth token
        $token = new ApiTokenAuthenticatedToken($this->user, 'main', ['ROLE_USER'], $api_token);

        //The permissions should be readonly
        $this->assertTrue($this->service->isGrantedTrinary($token, 'parts', 'read'));
        $this->assertFalse($this->service->isGrantedTrinary($token, 'parts', 'edit'));
        $this->assertFalse($this->service->isGrantedTrinary($token, 'parts', 'create'));
        $this->assertFalse($this->service->isGrantedTrinary($token, 'footprints', 'edit'));
    }

    public function testIsGrantedTrinaryAdminAPI(): void
    {
        //Create a API token
        $api_token = new ApiToken();
        $api_token->setLevel(ApiTokenLevel::FULL)->setName('Test Token');
        //Create an auth token
        $token = new ApiTokenAuthenticatedToken($this->user, 'main', ['ROLE_USER'], $api_token);

        //The permissions should be readonly
        $this->assertTrue($this->service->isGrantedTrinary($token, 'parts', 'read'));
        $this->assertFalse($this->service->isGrantedTrinary($token, 'parts', 'edit'));
        $this->assertNull($this->service->isGrantedTrinary($token, 'parts', 'create'));
        $this->assertTrue($this->service->isGrantedTrinary($token, 'footprints', 'edit'));
    }


    public function testIsGrantedNonAPI(): void
    {
        //Same as testIsGrantedTrinaryNonAPI, but every non-true value should return false.
        $token = new UsernamePasswordToken($this->user, 'main');
        $this->assertTrue($this->service->isGranted($token, 'parts', 'read'));
        $this->assertFalse($this->service->isGranted($token, 'parts', 'edit'));
        $this->assertFalse($this->service->isGranted($token, 'parts', 'create'));
    }
}
