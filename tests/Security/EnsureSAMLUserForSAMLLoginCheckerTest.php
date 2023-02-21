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
use App\Security\EnsureSAMLUserForSAMLLoginChecker;
use Hslavich\OneloginSamlBundle\Security\Http\Authenticator\Token\SamlToken;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

class EnsureSAMLUserForSAMLLoginCheckerTest extends WebTestCase
{
    /** @var EnsureSAMLUserForSAMLLoginChecker */
    protected $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get('saml_user_factory');
    }

    public function testOnAuthenticationSuccessFailsOnSSOLoginWithLocalUser(): void
    {
        $local_user = new User();

        $saml_token = $this->createMock(SamlToken::class);
        $saml_token->method('getUser')->willReturn($local_user);

        $event = new AuthenticationSuccessEvent($saml_token);

        $this->expectException(CustomUserMessageAccountStatusException::class);

        $this->service->onAuthenticationSuccess($event);
    }

    public function testOnAuthenticationSuccessFailsOnLocalLoginWithSAMLUser(): void
    {
        $saml_user = (new User())->setSamlUser(true);

        $saml_token = $this->createMock(UsernamePasswordToken::class);
        $saml_token->method('getUser')->willReturn($saml_user);

        $event = new AuthenticationSuccessEvent($saml_token);

        $this->expectException(CustomUserMessageAccountStatusException::class);

        $this->service->onAuthenticationSuccess($event);
    }
}
