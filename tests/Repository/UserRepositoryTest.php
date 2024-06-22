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
namespace App\Tests\Repository;

use App\Entity\UserSystem\User;
use App\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UserRepositoryTest extends WebTestCase
{

    private $entityManager;
    /**
     * @var UserRepository
     */
    private $repo;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();

        $this->repo = $this->entityManager->getRepository(User::class);
    }


    public function testGetAnonymousUser()
    {
        $user = $this->repo->getAnonymousUser();

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame(User::ID_ANONYMOUS, $user->getId());
        $this->assertSame('anonymous', $user->getUsername());
    }

    public function testFindByEmailOrName()
    {
        //Test for email
        $u = $this->repo->findByEmailOrName('user@invalid.invalid');
        $this->assertInstanceOf(User::class, $u);
        $this->assertSame('user', $u->getUsername());

        //Test for name
        $u = $this->repo->findByEmailOrName('user');
        $this->assertInstanceOf(User::class, $u);
        $this->assertSame('user', $u->getUsername());

        //Check what happens for unknown user
        $u = $this->repo->findByEmailOrName('unknown');
        $this->assertNull($u);

    }

    public function testFindByUsername()
    {
        $u = $this->repo->findByUsername('user');
        $this->assertInstanceOf(User::class, $u);
        $this->assertSame('user', $u->getUsername());

        //Check what happens for unknown user
        $u = $this->repo->findByEmailOrName('unknown');
        $this->assertNull($u);
    }
}
