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

namespace App\Tests;

use Generator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * This test just ensures that different pages are available (do not throw an exception).
 *
 * @group DB
 * @group slow
 */
class ApplicationAvailabilityFunctionalTest extends WebTestCase
{
    /**
     * @dataProvider urlProvider
     */
    public function testPageIsSuccessful(string $url): void
    {
        //We have localized routes
        $url = '/en'.$url;

        //Try to access pages with admin, because he should be able to view every page!
        static::ensureKernelShutdown();
        $client = static::createClient([], [
            'PHP_AUTH_USER' => 'admin',
            'PHP_AUTH_PW' => 'test',
        ]);
        $client->catchExceptions(false);

        $client->request('GET', $url);

        $this->assertTrue($client->getResponse()->isSuccessful(), 'Request not successful. Status code is '.$client->getResponse()->getStatusCode() . ' for URL '.$url);
    }

    public function urlProvider(): ?Generator
    {
        //Homepage
        yield ['/'];
        //User related things
        yield ['/user/settings'];
        yield ['/user/info'];

        //Login/logout
        yield ['/login'];

        //Tree views
        yield ['/tree/tools'];
        yield ['/tree/category/1'];
        yield ['/tree/categories'];
        yield ['/tree/footprint/1'];
        yield ['/tree/footprints'];
        yield ['/tree/location/1'];
        yield ['/tree/locations'];
        yield ['/tree/manufacturer/1'];
        yield ['/tree/manufacturers'];
        yield ['/tree/supplier/1'];
        yield ['/tree/suppliers'];
        //yield ['/tree/device/1'];
        yield ['/tree/devices'];

        //Part tests
        yield ['/part/1'];
        yield ['/part/2'];
        yield ['/part/3'];

        yield ['/part/1/edit'];
        yield ['/part/2/edit'];
        yield ['/part/3/edit'];

        yield ['/part/3/clone'];

        yield ['/part/new'];
        yield ['/part/new?category=1&footprint=1&manufacturer=1&storelocation=1&supplier=1'];

        //Statistics
        yield ['/statistics'];

        //Event log
        yield ['/log/']; //Slash suffix here is important


        //Typeahead
        yield ['/typeahead/builtInResources/search?query=DIP8'];
        yield ['/typeahead/tags/search/test'];
        yield ['/typeahead/parameters/part/search/NPN'];
        yield ['/typeahead/parameters/category/search/NPN'];

        //Select API
        yield ['/select_api/category'];
        yield ['/select_api/footprint'];
        yield ['/select_api/manufacturer'];
        yield ['/select_api/measurement_unit'];

        //Label test
        yield ['/scan'];
        yield ['/label/dialog'];
        yield ['/label/dialog?target_id=1&target_type=part'];
        yield ['/label/1/dialog'];
        yield ['/label/1/dialog?target_id=1&target_type=part&generate=1'];

        //Tools
        yield ['/tools/reel_calc'];
        yield ['/tools/server_infos'];
        yield ['/tools/builtin_footprints'];
        yield ['/tools/ic_logos'];

        //Webauthn Register
        yield ['/webauthn/register'];

        //Projects
        yield ['/project/1/info'];
        yield ['/project/1/add_parts'];
        yield ['/project/1/add_parts?parts=1,2'];
        yield ['/project/1/build?n=1'];
    }
}
