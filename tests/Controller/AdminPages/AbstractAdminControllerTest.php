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

namespace App\Tests\Controller\AdminPages;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @group slow
 * @group DB
 */
abstract class AbstractAdminControllerTest extends WebTestCase
{
    protected static $base_path = 'not_valid';
    protected static $entity_class = 'not valid';

    public function readDataProvider(): array
    {
        return [
            ['noread', false],
            ['anonymous', true],
            ['user', true],
            ['admin', true],
        ];
    }

    /**
     * @dataProvider readDataProvider
     * @group slow
     * Tests if you can access the /new part which is used to list all entities. Checks if permissions are working
     */
    public function testListEntries(string $user, bool $read): void
    {
        static::ensureKernelShutdown();

        //Test read access
        $client = static::createClient([], [
            'PHP_AUTH_USER' => $user,
            'PHP_AUTH_PW' => 'test',
        ]);

        $client->catchExceptions(false);
        if (false === $read) {
            $this->expectException(AccessDeniedException::class);
        }

        $client->catchExceptions(false);

        //Test read/list access by access /new overview page
        $client->request('GET', static::$base_path.'/new');
        $this->assertFalse($client->getResponse()->isRedirect());
        $this->assertSame($read, $client->getResponse()->isSuccessful(), 'Controller was not successful!');
        $this->assertSame($read, ! $client->getResponse()->isForbidden(), 'Permission Checking not working!');
    }

    /**
     * @dataProvider readDataProvider
     * @group slow
     * Tests if it possible to access an specific entity. Checks if permissions are working.
     */
    public function testReadEntity(string $user, bool $read): void
    {
        //Test read access
        $client = static::createClient([], [
            'PHP_AUTH_USER' => $user,
            'PHP_AUTH_PW' => 'test',
        ]);

        $client->catchExceptions(false);
        if (false === $read) {
            $this->expectException(AccessDeniedException::class);
        }

        //Test read/list access by access /new overview page
        $client->request('GET', static::$base_path.'/1');
        $this->assertFalse($client->getResponse()->isRedirect());
        $this->assertSame($read, $client->getResponse()->isSuccessful(), 'Controller was not successful!');
        $this->assertSame($read, ! $client->getResponse()->isForbidden(), 'Permission Checking not working!');
    }

    public function deleteDataProvider(): array
    {
        return [
            ['noread', false],
            ['anonymous', false],
            ['user', true],
            ['admin', true],
        ];
    }

    /**
     * Tests if deleting an entity is working.
     *
     * @group slow
     * @dataProvider deleteDataProvider
     */
    public function testDeleteEntity(string $user, bool $delete): void
    {
        //Test read access
        $client = static::createClient([], [
            'PHP_AUTH_USER' => $user,
            'PHP_AUTH_PW' => 'test',
        ]);

        $client->catchExceptions(false);
        if (false === $delete) {
            $this->expectException(AccessDeniedException::class);
        }

        //Test read/list access by access /new overview page
        $client->request('DELETE', static::$base_path.'/7');

        //Page is redirected to '/new', when delete was successful
        $this->assertSame($delete, $client->getResponse()->isRedirect(static::$base_path.'/new'));
        $this->assertSame($delete, ! $client->getResponse()->isForbidden(), 'Permission Checking not working!');
    }
}
