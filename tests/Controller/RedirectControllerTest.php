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

namespace App\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Proxies\__CG__\App\Entity\UserSystem\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * @group slow
 * @group DB
 */
class RedirectControllerTest extends WebTestCase
{
    protected $em;
    protected $userRepo;
    protected $client;

    public function setUp() : void
    {
        $this->client = static::createClient([], [
            'PHP_AUTH_USER' => 'user',
            'PHP_AUTH_PW' => 'test',
        ]);
        $this->client->disableReboot();
        $this->em = self::$container->get(EntityManagerInterface::class);
        $this->userRepo = $this->em->getRepository(User::class);
    }

    public function urlMatchDataProvider()
    {
        return [
            ['/', true],
            ['/part/2/info', true],
            ['/part/de/2', true],
            ['/part/en/', true],
            ['/de/', false],
            ['/de_DE/', false],
            ['/en/', false],
            ['/en_US/', false],
        ];
    }

    /**
     * Test if a certain request to an url will be redirected.
     *
     * @dataProvider urlMatchDataProvider
     * @group slow
     */
    public function testUrlMatch($url, $expect_redirect)
    {
        //$client = static::createClient();
        $this->client->request('GET', $url);
        $response = $this->client->getResponse();
        if ($expect_redirect) {
            $this->assertEquals(302, $response->getStatusCode());
        }
        $this->assertEquals($expect_redirect, $response->isRedirect());
    }

    public function urlAddLocaleDataProvider()
    {
        return [
            //User locale, original target, redirect target
            ['de', '/', '/de/'],
            ['de', '/part/3', '/de/part/3'],
            ['en', '/', '/en/'],
            ['en', '/category/new', '/en/category/new'],
            ['en_US', '/part/3', '/en_US/part/3'],
            //Without an explicit set value, the user should be redirect to english version
            [null, '/', '/en/'],
            ['en_US', '/part/3', '/en_US/part/3'],
        ];
    }

    /**
     * Test if the user is redirected to the localized version of a page, based on his settings.
     *
     * @dataProvider urlAddLocaleDataProvider
     * @group slow
     * @depends testUrlMatch
     *
     * @param $user_locale
     * @param $input_path
     * @param $redirect_path
     */
    public function testAddLocale($user_locale, $input_path, $redirect_path)
    {
        //Redirect path is absolute
        $redirect_path = 'http://localhost'.$redirect_path;

        /** @var User $user */
        $user = $this->userRepo->findOneBy(['name' => 'user']);
        //Set user locale
        $user->setLanguage($user_locale);
        $this->em->flush();



        $this->client->followRedirects(false);
        $this->client->request('GET', $input_path);
        $this->assertEquals($redirect_path, $this->client->getResponse()->headers->get('Location'));
    }
}
