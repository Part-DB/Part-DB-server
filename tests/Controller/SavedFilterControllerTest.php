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

use App\Entity\UserSystem\SavedFilter;
use App\Entity\UserSystem\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('DB')]
final class SavedFilterControllerTest extends WebTestCase
{
    private const FILTER_QUERY = 'part_filter%5Bname%5D%5Boperator%5D=%3D&part_filter%5Bname%5D%5Bvalue%5D=Part%201&part_filter%5Bsubmit%5D=';

    private string $defaultTimezone = 'UTC';

    protected function setUp(): void
    {
        //The timezone of the logged-in user is set as the default timezone of PHP
        $this->defaultTimezone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->defaultTimezone);
        parent::tearDown();
    }

    private function createClientAs(string $username): KernelBrowser
    {
        $client = static::createClient();
        $client->loginUser($this->getUser($username));

        return $client;
    }

    private function getUser(string $username): User
    {
        $user = static::getContainer()->get(EntityManagerInterface::class)->getRepository(User::class)
            ->findOneBy(['name' => $username]);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function createFilter(string $owner, bool $shared, string $name = 'Test filter'): SavedFilter
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $filter = (new SavedFilter())
            ->setName($name)
            ->setOwner($this->getUser($owner))
            ->setShared($shared)
            ->setTarget('part_list_category', ['id' => 1, '_locale' => 'en'], ['part_filter' => ['favorite' => ['value' => 'true']]]);
        $entityManager->persist($filter);
        $entityManager->flush();

        return $filter;
    }

    private function getCsrfToken(KernelBrowser $client, string $partList): string
    {
        $crawler = $client->request('GET', $partList);
        self::assertResponseIsSuccessful();

        return (string) $crawler->filter('form[action$="/saved_filters/save"] input[name="_token"]')->attr('value');
    }

    private function findFilter(string $name): ?SavedFilter
    {
        return static::getContainer()->get(EntityManagerInterface::class)->getRepository(SavedFilter::class)
            ->findOneBy(['name' => $name]);
    }

    public function testSharedDateTimesKeepTheirPointInTime(): void
    {
        $client = $this->createClientAs('admin');
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->getUser('admin')->setTimezone('Europe/Berlin');
        $this->getUser('user')->setTimezone('America/New_York');
        $entityManager->flush();

        $query = http_build_query(['part_filter' => ['lastModified' => ['operator' => '>', 'value1' => '2026-01-15T12:00']]]);
        $client->request('POST', '/en/saved_filters/save', [
            '_token' => $this->getCsrfToken($client, '/en/parts?'.$query),
            'route' => 'parts_show_all',
            'route_params' => '{}',
            'query' => $query,
            'name' => 'Changed recently',
            'shared' => '1',
        ]);
        self::assertResponseRedirects();
        //The owner gets back the time they entered
        self::assertStringContainsString('[value1]=2026-01-15T12:00', urldecode((string) $client->getResponse()->headers->get('Location')));

        $filter = $this->findFilter('Changed recently');
        self::assertInstanceOf(SavedFilter::class, $filter);
        self::assertTrue($filter->isShared());

        //Noon in Berlin is 6:00 in New York
        $client->loginUser($this->getUser('user'));
        $client->request('GET', '/en/saved_filters/'.$filter->getId());
        self::assertResponseRedirects();
        self::assertStringContainsString('[value1]=2026-01-15T06:00', urldecode((string) $client->getResponse()->headers->get('Location')));
    }

    public function testDeletingTheOwnerRemovesTheSharedFiltersFromTheTrees(): void
    {
        $client = $this->createClientAs('admin');
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $owner = (new User())->setName('filter_owner');
        $entityManager->persist($owner);
        $entityManager->flush();
        $this->createFilter('filter_owner', true, 'Filter of a deleted user');

        $client->request('GET', '/en/tree/tools');
        self::assertStringContainsString('Filter of a deleted user', (string) $client->getResponse()->getContent());

        //The database deletes the filters of the user, so no entity listener of the filters is called
        $entityManager->remove($entityManager->find(User::class, $owner->getID()));
        $entityManager->flush();
        $entityManager->clear();

        $client->request('GET', '/en/tree/tools');
        self::assertStringNotContainsString('Filter of a deleted user', (string) $client->getResponse()->getContent());
    }

    public function testUserSavesTheAppliedFilterAndOpensItAgain(): void
    {
        $client = $this->createClientAs('user');

        $crawler = $client->request('GET', '/en/parts?'.self::FILTER_QUERY);
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[action$="/saved_filters/save"]');
        self::assertCount(1, $form);
        //Users without the share permission can only save their own filters
        self::assertCount(0, $form->filter('input[name="shared"]'));

        $client->submit($form->form(['name' => 'My parts']));
        self::assertResponseRedirects();
        self::assertStringContainsString('/en/parts?', (string) $client->getResponse()->headers->get('Location'));

        $filter = $this->findFilter('My parts');
        self::assertInstanceOf(SavedFilter::class, $filter);
        self::assertSame('user', $filter->getOwner()?->getUsername());
        self::assertFalse($filter->isShared());
        self::assertSame('parts_show_all', $filter->getRoute());
        self::assertSame([], $filter->getRouteParams());
        self::assertSame(['operator' => '=', 'value' => 'Part 1'], $filter->getQuery()['part_filter']['name']);

        $client->request('GET', '/en/saved_filters/'.$filter->getId());
        self::assertResponseRedirects();
        $location = urldecode((string) $client->getResponse()->headers->get('Location'));
        self::assertStringStartsWith('/en/parts?', $location);
        self::assertStringContainsString('part_filter[name][value]=Part 1', $location);

        //The saved filter is listed in the tools tree and in the filter of the part lists
        $client->request('GET', '/en/tree/tools');
        self::assertStringContainsString('My parts', (string) $client->getResponse()->getContent());
        $crawler = $client->request('GET', '/en/parts');
        self::assertCount(1, $crawler->filter('a[href$="/saved_filters/'.$filter->getId().'"]'));
    }

    public function testFilterOfAPartListPageKeepsItsRouteParameters(): void
    {
        $client = $this->createClientAs('user');
        $filter = $this->createFilter('user', false);

        self::assertSame(['id' => 1], $filter->getRouteParams());

        $client->request('GET', '/de/saved_filters/'.$filter->getId());
        self::assertResponseRedirects();
        //The filter opens in the language of the user, who opens it
        self::assertStringStartsWith('/de/category/1/parts?part_filter', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testAdminCanShareFilters(): void
    {
        $client = $this->createClientAs('admin');

        $crawler = $client->request('GET', '/en/parts?'.self::FILTER_QUERY);
        $client->submit($crawler->filter('form[action$="/saved_filters/save"]')->form(['name' => 'Shared parts', 'shared' => '1']));

        $filter = $this->findFilter('Shared parts');
        self::assertInstanceOf(SavedFilter::class, $filter);
        self::assertTrue($filter->isShared());
    }

    public function testSharedFlagIsIgnoredWithoutSharePermission(): void
    {
        $client = $this->createClientAs('user');

        $crawler = $client->request('GET', '/en/parts?'.self::FILTER_QUERY);
        $token = $crawler->filter('form[action$="/saved_filters/save"] input[name="_token"]')->attr('value');

        $client->request('POST', '/en/saved_filters/save', [
            '_token' => $token,
            'name' => 'Not shared',
            'shared' => '1',
            'route' => 'parts_show_all',
            'route_params' => '{}',
            'query' => self::FILTER_QUERY,
        ]);

        $filter = $this->findFilter('Not shared');
        self::assertInstanceOf(SavedFilter::class, $filter);
        self::assertFalse($filter->isShared());
    }

    public function testOnlyPartListsCanBeSaved(): void
    {
        $client = $this->createClientAs('user');

        $crawler = $client->request('GET', '/en/parts?'.self::FILTER_QUERY);
        $token = $crawler->filter('form[action$="/saved_filters/save"] input[name="_token"]')->attr('value');

        $client->request('POST', '/en/saved_filters/save', [
            '_token' => $token,
            'name' => 'Not a part list',
            'route' => 'user_settings',
            'route_params' => '{}',
            'query' => 'foo=bar',
        ]);
        self::assertResponseRedirects();
        self::assertNull($this->findFilter('Not a part list'));

        //Without an applied filter, there is nothing to save
        $client->request('POST', '/en/saved_filters/save', [
            '_token' => $token,
            'name' => 'Without filter',
            'route' => 'parts_show_all',
            'route_params' => '{}',
            'query' => '',
        ]);
        self::assertNull($this->findFilter('Without filter'));
    }

    public function testSaveNeedsAValidCsrfToken(): void
    {
        $client = $this->createClientAs('user');

        $client->request('POST', '/en/saved_filters/save', [
            '_token' => 'invalid',
            'name' => 'Invalid token',
            'route' => 'parts_show_all',
            'route_params' => '{}',
            'query' => self::FILTER_QUERY,
        ]);
        self::assertResponseRedirects();
        self::assertNull($this->findFilter('Invalid token'));
    }

    public function testUserWithoutPartAccessCanNotSaveFilters(): void
    {
        $client = $this->createClientAs('noread');

        $client->request('POST', '/en/saved_filters/save', [
            'name' => 'No access',
            'route' => 'parts_show_all',
            'route_params' => '{}',
            'query' => self::FILTER_QUERY,
        ]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testOwnFiltersAreHiddenFromOtherUsers(): void
    {
        $client = $this->createClientAs('user');
        $filter = $this->createFilter('admin', false, 'Private filter of the admin');

        $client->request('GET', '/en/saved_filters/'.$filter->getId());
        self::assertResponseStatusCodeSame(403);

        $client->request('GET', '/en/saved_filters');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('Private filter of the admin', (string) $client->getResponse()->getContent());
    }

    public function testSharedFiltersCanBeOpenedByAllButOnlyChangedWithSharePermission(): void
    {
        $client = $this->createClientAs('user');
        $filter = $this->createFilter('admin', true, 'Shared filter of the admin');

        $client->request('GET', '/en/saved_filters');
        self::assertStringContainsString('Shared filter of the admin', (string) $client->getResponse()->getContent());

        $client->request('GET', '/en/saved_filters/'.$filter->getId());
        self::assertResponseRedirects();

        $client->request('GET', '/en/saved_filters/'.$filter->getId().'/edit');
        self::assertResponseStatusCodeSame(403);

        $client->request('POST', '/en/saved_filters/'.$filter->getId().'/delete');
        self::assertResponseStatusCodeSame(403);
    }

    public function testOwnerRenamesAndDeletesTheirFilter(): void
    {
        $client = $this->createClientAs('user');
        $filter = $this->createFilter('user', false, 'Old name');
        $id = $filter->getId();

        $crawler = $client->request('GET', '/en/saved_filters/'.$id.'/edit');
        self::assertResponseIsSuccessful();
        //Users without the share permission can not share their filters
        self::assertCount(0, $crawler->filter('input[name="form[shared]"]'));

        $client->submit($crawler->filter('form[name="form"]')->form(['form[name]' => 'New name']));
        self::assertResponseRedirects('/en/saved_filters');
        self::assertInstanceOf(SavedFilter::class, $this->findFilter('New name'));

        $crawler = $client->request('GET', '/en/saved_filters');
        $client->submit($crawler->filter('form[action$="/saved_filters/'.$id.'/delete"]')->form());
        self::assertResponseRedirects('/en/saved_filters');
        self::assertNull($this->findFilter('New name'));
    }
}
