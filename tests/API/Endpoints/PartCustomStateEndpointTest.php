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

final class PartCustomStateEndpointTest extends CrudEndpointTestCase
{

    protected function getBasePath(): string
    {
        return '/api/part_custom_states';
    }

    public function testGetCollection(): void
    {
        $this->_testGetCollection();
        self::assertJsonContains([
            'hydra:totalItems' => 7,
        ]);
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
            'parent' => '/api/part_custom_states/1',
        ]);
    }

    public function testUpdateItem(): void
    {
        $this->_testPatchItem(5, [
            'name' => 'Updated',
            'parent' => '/api/part_custom_states/2',
        ]);
    }

    public function testDeleteItem(): void
    {
        $this->_testDeleteItem(4);
    }

    public function testColorIsWritableAndReadable(): void
    {
        $this->_testPatchItem(5, [
            'color' => 'warning',
        ]);
        self::assertJsonContains([
            'color' => 'warning',
        ]);

        //Unsetting the color must be possible again (back to the default, uncolored appearance).
        //Like every other null-valued field in this API, an unset color is omitted from the response entirely
        //rather than being serialized as an explicit "color": null (see e.g. InfoProviderEndpointTest).
        $response = $this->_testPatchItem(5, [
            'color' => null,
        ]);
        self::assertArrayNotHasKey('color', json_decode($response->getContent(), true));
    }
}
