<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Tests\Controller;

use App\Entity\UserSystem\User;
use App\Services\InfoProviderSystem\Providers\EmptyProvider;
use App\Services\InfoProviderSystem\Providers\TestProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group("slow")]
#[Group("DB")]
final class InfoProviderDirectAccessTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $user = static::getContainer()->get('doctrine')->getManager()->getRepository(User::class)->findOneBy(['name' => 'admin']);
        $this->client->loginUser($user);
    }

    public function testSearchPageLinksToDirectAccess(): void
    {
        $crawler = $this->client->request('GET', '/en/tools/info_providers/search');
        self::assertResponseIsSuccessful();

        self::assertCount(1, $crawler->filter('a[href="/en/tools/info_providers/direct"]'));
    }

    public function testSearchWithoutResultsLinksToPrefilledDirectAccess(): void
    {
        $crawler = $this->client->request('GET', '/en/tools/info_providers/search');

        $form = $crawler->filter('form[name="part_search"]')->form();
        $form['part_search[keyword]'] = 'FIX-GR-67';
        $form['part_search[providers]'] = [EmptyProvider::PROVIDER_KEY];
        $crawler = $this->client->submit($form);
        self::assertResponseIsSuccessful();

        $link = $crawler->filter('.alert-info a')->link()->getUri();
        self::assertStringContainsString('/en/tools/info_providers/direct?', $link);

        //The direct access form is prefilled with the search
        $crawler = $this->client->request('GET', $link);
        self::assertResponseIsSuccessful();
        self::assertSame('FIX-GR-67', $crawler->filter('#direct_access_form_provider_id')->attr('value'));
        self::assertSame(EmptyProvider::PROVIDER_KEY,
            $crawler->filter('#direct_access_form_provider option[selected]')->attr('value'));
    }

    public function testShowsErrorIfPartCanNotBeRetrieved(): void
    {
        $crawler = $this->client->request('GET', '/en/tools/info_providers/direct');

        $form = $crawler->filter('form[name="direct_access_form"]')->form();
        $form['direct_access_form[provider]'] = EmptyProvider::PROVIDER_KEY;
        $form['direct_access_form[provider_id]'] = 'unknown';
        $crawler = $this->client->submit($form);

        //No redirect, the form is shown again with an error
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('form[name="direct_access_form"]'));
    }

    public function testRedirectsToPartCreation(): void
    {
        $crawler = $this->client->request('GET', '/en/tools/info_providers/direct');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="direct_access_form"]')->form();
        $form['direct_access_form[provider]'] = TestProvider::PROVIDER_KEY;
        //Surrounding whitespace is removed, slashes are allowed in the ID
        $form['direct_access_form[provider_id]'] = ' ABC/123 ';
        $this->client->submit($form);

        self::assertResponseRedirects('/en/part/from_info_provider/test/ABC/123/create');
    }

    public function testEmptyIdIsRejected(): void
    {
        $crawler = $this->client->request('GET', '/en/tools/info_providers/direct');

        $form = $crawler->filter('form[name="direct_access_form"]')->form();
        $form['direct_access_form[provider]'] = TestProvider::PROVIDER_KEY;
        $form['direct_access_form[provider_id]'] = '';
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
    }
}
