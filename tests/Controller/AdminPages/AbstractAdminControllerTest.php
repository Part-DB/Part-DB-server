<?php
/**
 *
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/
 *
 * part-db version 0.2+
 * Copyright (C) 2009 K. Jacobs and others (see authors.php)
 * http://code.google.com/p/part-db/
 *
 * Part-DB Version 0.4+
 * Copyright (C) 2016 - 2019 Jan Böhmer
 * https://github.com/jbtronics
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
 *
 */

namespace App\Tests\Controller\AdminPages;


use App\Entity\Attachments\AttachmentType;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;

/**
 * @group slow
 * @package App\Tests\Controller\AdminPages
 */
abstract class AbstractAdminControllerTest extends WebTestCase
{
    protected static $base_path = 'not_valid';
    protected static $entity_class = 'not valid';


    public function setUp()
    {
        parent::setUp();
        self::bootKernel();
    }

    public function readDataProvider()
    {
        return [
            ['noread', false],
            ['anonymous', true],
            ['user', true],
            ['admin', true]
        ];
    }

    /**
     * @dataProvider readDataProvider
     * @group slow
     * Tests if you can access the /new part which is used to list all entities. Checks if permissions are working
     */
    public function testListEntries(string $user, bool $read)
    {
        //Test read access
        $client = static::createClient([], [
            'PHP_AUTH_USER' => $user,
            'PHP_AUTH_PW'   => 'test',
        ]);

        if ($read == false) {
            $this->expectException(AccessDeniedException::class);
        }

        $client->catchExceptions(false);

        //Test read/list access by access /new overview page
        $crawler = $client->request('GET', static::$base_path . '/new');
        $this->assertFalse($client->getResponse()->isRedirect());
        $this->assertEquals($read, $client->getResponse()->isSuccessful(), "Controller was not successful!");
        $this->assertEquals($read, !$client->getResponse()->isForbidden(), "Permission Checking not working!");
    }

    /**
     * @dataProvider readDataProvider
     * @group slow
     * Tests if it possible to access an specific entity. Checks if permissions are working.
     */
    public function testReadEntity(string $user, bool $read)
    {
        //Test read access
        $client = static::createClient([], [
            'PHP_AUTH_USER' => $user,
            'PHP_AUTH_PW'   => 'test',
        ]);

        $client->catchExceptions(false);
        if ($read == false) {
            $this->expectException(AccessDeniedException::class);
        }

        //Test read/list access by access /new overview page
        $crawler = $client->request('GET', static::$base_path . '/1');
        $this->assertFalse($client->getResponse()->isRedirect());
        $this->assertEquals($read, $client->getResponse()->isSuccessful(), "Controller was not successful!");
        $this->assertEquals($read, !$client->getResponse()->isForbidden(), "Permission Checking not working!");
    }

    public function deleteDataProvider()
    {
        return [
            ['noread', false],
            ['anonymous', false],
            ['user', true],
            ['admin', true]
        ];
    }

    /**
     * Tests if deleting an entity is working.
     * @group slow
     * @dataProvider deleteDataProvider
     */
    public function testDeleteEntity(string $user, bool $delete)
    {
        //Test read access
        $client = static::createClient([], [
            'PHP_AUTH_USER' => $user,
            'PHP_AUTH_PW'   => 'test',
        ]);

        $client->catchExceptions(false);
        if ($delete == false) {
           $this->expectException(AccessDeniedException::class);
        }

        //Test read/list access by access /new overview page
        $crawler = $client->request('DELETE', static::$base_path . '/7');

        //Page is redirected to '/new', when delete was successful
        $this->assertEquals($delete, $client->getResponse()->isRedirect(static::$base_path . '/new'));
        $this->assertEquals($delete, !$client->getResponse()->isForbidden(), "Permission Checking not working!");
    }

}