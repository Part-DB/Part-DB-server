<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use App\Entity\UserSystem\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * @group slow
 * @group DB
 */
class RedirectControllerTest extends WebTestCase
{
    protected EntityManagerInterface $em;
    protected UserRepository $userRepo;
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient([], [
            'PHP_AUTH_USER' => 'user',
            'PHP_AUTH_PW' => 'test',
        ]);
        $this->client->disableReboot();
        $this->client->catchExceptions(false);
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->userRepo = $this->em->getRepository(User::class);
    }

    public function urlMatchDataProvider(): \Iterator
    {
        yield ['/', true];
        yield ['/part/2/info', true];
        yield ['/part/de/2', true];
        yield ['/part/en/', true];
        yield ['/de/', false];
        yield ['/de_DE/', false];
        yield ['/en/', false];
        yield ['/en_US/', false];
    }

    /**
     * Test if a certain request to an url will be redirected.
     *
     * @dataProvider urlMatchDataProvider
     * @group slow
     */
    public function testUrlMatch($url, $expect_redirect): void
    {
        //$client = static::createClient();
        $this->client->request('GET', $url);
        $response = $this->client->getResponse();
        if ($expect_redirect) {
            $this->assertResponseStatusCodeSame(302);
        }
        $this->assertSame($expect_redirect, $response->isRedirect());
    }

    public function urlAddLocaleDataProvider(): \Iterator
    {
        //User locale, original target, redirect target
        yield ['de', '/', '/de/'];
        yield ['de', '/part/3', '/de/part/3'];
        yield ['en', '/', '/en/'];
        yield ['en', '/category/new', '/en/category/new'];
        yield ['en_US', '/part/3', '/en_US/part/3'];
        //Without an explicit set value, the user should be redirected to english version
        yield [null, '/', '/en/'];
        yield ['en_US', '/part/3', '/en_US/part/3'];
        //Test that query parameters work
        yield ['de', '/dialog?target_id=133&target_type=part', '/de/dialog?target_id=133&target_type=part'];
        yield ['en', '/dialog?storelocation=1', '/en/dialog?storelocation=1'];
    }

    /**
     * Test if the user is redirected to the localized version of a page, based on his settings.
     *
     * @dataProvider urlAddLocaleDataProvider
     * @group slow
     * @depends      testUrlMatch
     */
    public function testAddLocale(?string $user_locale, string $input_path, string $redirect_path): void
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
        $this->assertResponseRedirects($redirect_path);
    }
}
