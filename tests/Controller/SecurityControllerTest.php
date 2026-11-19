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

declare(strict_types=1);

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

#[Group('slow')]
#[Group('DB')]
final class SecurityControllerTest extends WebTestCase
{
    public function testTargetPathIsKeptAfterFailedLogin(): void
    {
        $client = static::createClient();
        $client->followRedirects(false);

        $crawler = $client->request('GET', '/en/login?_target_path=/en/part/1/info');
        $this->assertResponseIsSuccessful();
        $this->assertSame('/en/part/1/info', $crawler->filter('input[name="_target_path"]')->attr('value'));

        //Submit a wrong password
        $form = $crawler->selectButton('submit')->form([
            '_username' => 'admin',
            '_password' => 'wrong',
        ]);
        $client->submit($form);

        //We must land on the login page again, with the target path still present
        $this->assertResponseRedirects();
        $location = $client->getResponse()->headers->get('Location');
        $this->assertStringContainsString('/en/login?_target_path=/en/part/1/info', $location);

        $crawler = $client->request('GET', $location);
        $this->assertSame('/en/part/1/info', $crawler->filter('input[name="_target_path"]')->attr('value'));

        //Now login correctly, which should redirect to the target path
        $form = $crawler->selectButton('submit')->form([
            '_username' => 'admin',
            '_password' => 'test',
        ]);
        $client->submit($form);
        $this->assertResponseRedirects('/en/part/1/info');
    }

    public function testExternalTargetPathIsIgnored(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/en/login?_target_path=//evil.example.com/');
        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('input[name="_target_path"]'));
        $this->assertCount(0, $crawler->filter('input[name="_failure_path"]'));
    }

    public function testNavbarLoginLinkTargetsCurrentPage(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/en/part/1/info?foo=bar');
        $this->assertResponseIsSuccessful();

        //The link rendered in the navbar itself
        $this->assertLoginLinkTarget('/part/1/info?foo=bar', $crawler->filter('#navbar-frame #login-link')->attr('href'));

        //The turbo-stream inside the content frame, which replaces the navbar link on turbo navigation
        $stream = $crawler->filter('turbo-frame#content turbo-stream[target="login-link"]');
        $this->assertCount(1, $stream);
        $link = (new Crawler($stream->filter('template')->html()))->filter('#login-link');
        $this->assertLoginLinkTarget('/part/1/info?foo=bar', $link->attr('href'));
    }

    private function assertLoginLinkTarget(string $expectedTarget, ?string $href): void
    {
        $this->assertNotNull($href);
        $this->assertSame('/en/login', parse_url($href, PHP_URL_PATH));
        parse_str((string) parse_url($href, PHP_URL_QUERY), $query);
        $this->assertSame($expectedTarget, $query['_target_path'] ?? null);
    }

    public function testNavbarLoginLinkHasNoTargetOnLoginPage(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/en/login');
        $this->assertSame('/en/login', $crawler->filter('#navbar-frame #login-link')->attr('href'));
    }
}
