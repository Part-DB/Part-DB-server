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

namespace App\Tests;


use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DatatablesAvailabilityTest extends WebTestCase
{
    /**
     * @dataProvider urlProvider
     * @param  string  $url
     */
    public function testDataTable(string $url)
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

        $client->request('POST', $url, ['_dt' => 'dt']);
        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertJson($client->getResponse()->getContent());
    }

    public function urlProvider()
    {
        //Part lists
        yield ['/category/1/parts'];
        yield ['/footprint/1/parts'];
        yield ['/manufacturer/1/parts'];
        yield ['/store_location/1/parts'];
        yield ['/supplier/1/parts'];
        yield ['/parts/by_tag/Test'];
        yield ['/parts/search?keyword=test'];
        yield ['/parts'];

        yield ['/log/'];

        yield ['/attachment/list'];

    }
}