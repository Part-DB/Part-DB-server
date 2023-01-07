<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Tests\Entity\UserSystem;

use App\Entity\UserSystem\PermissionData;
use PHPUnit\Framework\TestCase;

class PermissionDataTest extends TestCase
{

    public function testGetSetIs()
    {
        $perm_data = new PermissionData();

        //Empty object should have all permissions set to inherit
        $this->assertNull($perm_data->getPermissionValue('not_existing', 'not_existing'));
        $this->assertFalse($perm_data->isPermissionSet('not_existing', 'not_existing'));

        $this->assertNull($perm_data->getPermissionValue('p1', 'op1'));
        $this->assertNull($perm_data->getPermissionValue('p1', 'op2'));
        $this->assertNull($perm_data->getPermissionValue('p2', 'op1'));

        //Set values
        $perm_data->setPermissionValue('p1', 'op1', PermissionData::ALLOW);
        $perm_data->setPermissionValue('p1', 'op2', PermissionData::DISALLOW);
        $perm_data->setPermissionValue('p2', 'op1', PermissionData::ALLOW);

        //Check that values were set
        $this->assertTrue($perm_data->isPermissionSet('p1', 'op1'));
        $this->assertTrue($perm_data->isPermissionSet('p1', 'op2'));
        $this->assertTrue($perm_data->isPermissionSet('p2', 'op1'));

        //Check that values are correct
        $this->assertTrue($perm_data->getPermissionValue('p1', 'op1'));
        $this->assertFalse($perm_data->getPermissionValue('p1', 'op2'));
        $this->assertTrue($perm_data->getPermissionValue('p2', 'op1'));

        //Set values to null
        $perm_data->setPermissionValue('p1', 'op1', null);
        $this->assertNull($perm_data->getPermissionValue('p1', 'op1'));
        //Values should be unset now
        $this->assertFalse($perm_data->isPermissionSet('p1', 'op1'));
    }

    public function testJSONSerialization()
    {
        $perm_data = new PermissionData();

        $perm_data->setPermissionValue('perm1', 'op1', PermissionData::ALLOW);
        $perm_data->setPermissionValue('perm1', 'op2', PermissionData::DISALLOW);
        $perm_data->setPermissionValue('perm1', 'op3', PermissionData::ALLOW);

        $perm_data->setPermissionValue('perm2', 'op1', PermissionData::ALLOW);
        $perm_data->setPermissionValue('perm2', 'op2', PermissionData::DISALLOW);

        //Ensure that JSON serialization works
        $this->assertJsonStringEqualsJsonString(json_encode([
            'perm1' => [
                'op1' => true,
                'op2' => false,
                'op3' => true,
            ],
            'perm2' => [
                'op1' => true,
                'op2' => false,
            ],
        ], JSON_THROW_ON_ERROR), json_encode($perm_data, JSON_THROW_ON_ERROR));

        //Set values to inherit to ensure they do not show up in the json
        $perm_data->setPermissionValue('perm1', 'op3', null);
        $perm_data->setPermissionValue('perm2', 'op1', null);
        $perm_data->setPermissionValue('perm2', 'op2', null);

        //Ensure that JSON serialization works
        $this->assertJsonStringEqualsJsonString(json_encode([
            'perm1' => [
                'op1' => true,
                'op2' => false,
            ],
        ], JSON_THROW_ON_ERROR), json_encode($perm_data, JSON_THROW_ON_ERROR));

    }

    public function testFromJSON()
    {
        $json = json_encode([
            'perm1' => [
                'op1' => true,
                'op2' => false,
                'op3' => true,
            ],
            'perm2' => [
                'op1' => true,
                'op2' => false,
            ],
        ], JSON_THROW_ON_ERROR);

        $perm_data = PermissionData::fromJSON($json);

        //Ensure that values were set correctly
        $this->assertTrue($perm_data->getPermissionValue('perm1', 'op1'));
        $this->assertFalse($perm_data->getPermissionValue('perm2', 'op2'));
    }

    public function testResetPermissions()
    {
        $data = new PermissionData();

        $data->setPermissionValue('perm1', 'op1', PermissionData::ALLOW);
        $data->setPermissionValue('perm1', 'op2', PermissionData::DISALLOW);
        $data->setPermissionValue('perm1', 'op3', PermissionData::INHERIT);

        //Ensure that values were set correctly
        $this->assertTrue($data->isPermissionSet('perm1', 'op1'));
        $this->assertTrue($data->isPermissionSet('perm1', 'op2'));
        $this->assertFalse($data->isPermissionSet('perm1', 'op3'));

        //Reset the permissions
        $data->resetPermissions();

        //Afterwards all values must be set to inherit (null)
        $this->assertNull($data->getPermissionValue('perm1', 'op1'));
        $this->assertNull($data->getPermissionValue('perm1', 'op2'));
        $this->assertNull($data->getPermissionValue('perm1', 'op3'));

        //And be undefined
        $this->assertFalse($data->isPermissionSet('perm1', 'op1'));
        $this->assertFalse($data->isPermissionSet('perm1', 'op2'));
        $this->assertFalse($data->isPermissionSet('perm1', 'op3'));
    }

    public function testGetSchemaVersion()
    {
        $data = new PermissionData();

        //By default the schema version must be the CURRENT_SCHEMA_VERSION
        $this->assertEquals(PermissionData::CURRENT_SCHEMA_VERSION, $data->getSchemaVersion());

        //Ensure that the schema version can be set
        $data->setSchemaVersion(12345);
        $this->assertEquals(12345, $data->getSchemaVersion());
    }
}
