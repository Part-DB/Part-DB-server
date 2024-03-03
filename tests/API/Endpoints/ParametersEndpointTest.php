<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Tests\API\Endpoints;

class ParametersEndpointTest extends CrudEndpointTestCase
{

    protected function getBasePath(): string
    {
        return '/api/parameters';
    }

    public function testElementLifecycle(): void
    {
        //Type should be automatically guessed from the element
        $this->_testPostItem([
            'name' => 'test',
            'element' => '/api/parts/1',
        ]);

        //Or manually set
        $response = $this->_testPostItem([
            'name' => 'test',
            'element' => '/api/footprints/1',
            '_type' => 'Footprint'
        ]);

        $id = $this->getIdOfCreatedElement($response);

        //Check if the new item is in the database
        $this->_testGetItem($id);

        //Check if we can change the item
        $this->_testPatchItem($id, [
            'name' => 'test2',
        ]);

        //Check if we can delete the item
        $this->_testDeleteItem($id);
    }
}