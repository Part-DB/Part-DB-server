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

class ProjectBOMEntriesEndpointTest extends CrudEndpointTestCase
{

    protected function getBasePath(): string
    {
        return '/api/project_bom_entries';
    }

    public function testGetCollection(): void
    {
        $this->_testGetCollection();
    }

    public function testItemLifecycle(): void
    {
        $response = $this->_testPostItem([
            'project' => '/api/projects/1',
            'part' => '/api/parts/1',
            'quantity' => 1,
        ]);

        $new_id = $this->getIdOfCreatedElement($response);

        //Check if the new item is in the database
        $this->_testGetItem($new_id);

        //Check if we can change the item
        $this->_testPatchItem($new_id, [
            'quantity' => 2,
        ]);

        //Check if we can delete the item
        $this->_testDeleteItem($new_id);
    }

    public function testGetBomOfProject(): void
    {
        $response = self::createAuthenticatedClient()->request('GET', '/api/projects/1/bom');
        self::assertResponseIsSuccessful();
    }
}