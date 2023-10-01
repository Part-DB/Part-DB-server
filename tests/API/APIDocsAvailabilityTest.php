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

declare(strict_types=1);


namespace API;

use App\Entity\UserSystem\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class APIDocsAvailabilityTest extends WebTestCase
{
    /**
     * @dataProvider urlProvider
     */
    public function testDocAvailabilityForLoggedInUser(string $url): void
    {
        self::ensureKernelShutdown();
        $client = static::createClient();
        $user = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(User::class)->findOneBy(['name' => 'admin']);
        $client->loginUser($user);

        $client->request('GET',$url);
        self::assertResponseIsSuccessful();
    }

    /**
     * @dataProvider urlProvider
     */
    public function testDocForbidden(string $url): void
    {
        self::ensureKernelShutdown();
        $client = static::createClient();
        $user = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(User::class)->findOneBy(['name' => 'noread']);
        $client->loginUser($user);

        $client->request('GET',$url);
        self::assertResponseStatusCodeSame(403);
    }

    public static function urlProvider(): array
    {
        return [
            ['/api'],
            ['/api/docs.html'],
            ['/api/docs.json'],
            //['/api/docs.jsonld'],
        ];
    }
}