<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Tests\API\Endpoints;

final class TagEndpointTest extends CrudEndpointTestCase
{
    protected function getBasePath(): string
    {
        return '/api/tags';
    }

    public function testGetCollection(): void
    {
        $this->_testGetCollection();
        //The seven tags of the data structure fixtures, and "test, Test, Part2" of a part, which are two tags
        self::assertJsonContains([
            'hydra:totalItems' => 9,
        ]);
    }

    public function testGetItem(): void
    {
        $this->_testGetItem(1);
        $this->_testGetItem(8);
        self::assertJsonContains(['name' => 'test']);
    }

    public function testCreateItem(): void
    {
        $this->_testPostItem([
            'name' => 'Created through API',
            'parent' => '/api/tags/1',
            'color' => 'success',
        ]);
        self::assertJsonContains(['color' => 'success']);
    }

    public function testCreatingAnExistingTagIsRejected(): void
    {
        //Tags are compared case-insensitively, so "TEST" is the existing tag "test"
        self::createAuthenticatedClient()->request('POST', $this->getBasePath(), ['json' => ['name' => 'TEST']]);
        self::assertResponseStatusCodeSame(422);

        self::createAuthenticatedClient()->request('POST', $this->getBasePath(), ['json' => ['name' => 'a, b']]);
        self::assertResponseStatusCodeSame(422);

        //Unlike for the other data structures, a name is unique across all tags, not only among the siblings, as
        //tags are assigned by their names
        self::createAuthenticatedClient()->request('POST', $this->getBasePath(), ['json' => ['name' => 'Node 1', 'parent' => '/api/tags/2']]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testUpdateItem(): void
    {
        $this->_testPatchItem(9, [
            'name' => 'Renamed',
            'color' => 'warning',
        ]);
        self::assertJsonContains(['color' => 'warning']);

        //Renaming a tag renames it on all parts
        self::createAuthenticatedClient()->request('GET', '/api/parts/2');
        self::assertJsonContains(['tags' => 'Renamed,test']);
    }

    public function testDeleteItem(): void
    {
        $this->_testDeleteItem(9);

        //Deleting a tag removes it from the parts
        self::createAuthenticatedClient()->request('GET', '/api/parts/2');
        self::assertJsonContains(['tags' => 'test']);
    }
}
