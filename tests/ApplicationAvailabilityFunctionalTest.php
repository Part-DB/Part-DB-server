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

namespace App\Tests;

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
        $client = static::createClient([], [
            'PHP_AUTH_USER' => 'admin',
            'PHP_AUTH_PW' => 'test',
        ]);

        $client->request('GET', $url);

        $this->assertTrue($client->getResponse()->isSuccessful(), 'Request not successful. Status code is '.$client->getResponse()->getStatusCode());
    }

    public function urlProvider()
    {
        //Homepage
        //yield ['/'];
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

        //Typeahead
        yield ['/typeahead/builtInResources/search/DIP8'];
        yield ['/typeahead/tags/search/test'];
    }
}
