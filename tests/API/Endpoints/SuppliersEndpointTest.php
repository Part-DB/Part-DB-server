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

use App\Tests\API\Endpoints\CrudEndpointTestCase;

class SuppliersEndpointTest extends CrudEndpointTestCase
{

    protected function getBasePath(): string
    {
        return '/api/suppliers';
    }

    public function testGetCollection(): void
    {
        $this->_testGetCollection();
        self::assertJsonContains([
            'hydra:totalItems' => 7,
        ]);
    }

    public function testGetChildrenCollection(): void
    {
        $this->_testGetChildrenCollection(1);
    }

    public function testGetItem(): void
    {
        $this->_testGetItem(1);
        $this->_testGetItem(2);
        $this->_testGetItem(3);
    }

    public function testCreateItem(): void
    {
        $this->_testPostItem([
            'name' => 'Test API',
            'parent' => '/api/suppliers/1',
        ]);
    }

    public function testUpdateItem(): void
    {
        $this->_testPatchItem(5, [
            'name' => 'Updated',
            'parent' => '/api/suppliers/2',
        ]);
    }

    public function testDeleteItem(): void
    {
        $this->_testDeleteItem(7);
    }
}