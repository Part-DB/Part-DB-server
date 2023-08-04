<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Tests\Helpers;

use App\Helpers\TrinaryLogicHelper;
use PHPUnit\Framework\TestCase;

class TrinaryLogicHelperTest extends TestCase
{

    public function testNot()
    {
        $this->assertTrue(TrinaryLogicHelper::not(false));
        $this->assertFalse(TrinaryLogicHelper::not(true));
        $this->assertNull(TrinaryLogicHelper::not(null));
    }

    public function testOr(): void
    {
        $this->assertFalse(TrinaryLogicHelper::or(false, false));
        $this->assertNull(TrinaryLogicHelper::or(null, false));
        $this->assertTrue(TrinaryLogicHelper::or(false, true));

        $this->assertNull(TrinaryLogicHelper::or(null, false));
        $this->assertNull(TrinaryLogicHelper::or(null, null));
        $this->assertTrue(TrinaryLogicHelper::or(false, true));

        $this->assertTrue(TrinaryLogicHelper::or(true, false));
        $this->assertTrue(TrinaryLogicHelper::or(true, null));
        $this->assertTrue(TrinaryLogicHelper::or(true, true));

        //Should work for longer arrays too
        $this->assertTrue(TrinaryLogicHelper::or(true, true, false, null));
        $this->assertNull(TrinaryLogicHelper::or(false, false, false, false, null));
        $this->assertFalse(TrinaryLogicHelper::or(false, false, false));

        //Test for one argument
        $this->assertTrue(TrinaryLogicHelper::or(true));
        $this->assertFalse(TrinaryLogicHelper::or(false));
        $this->assertNull(TrinaryLogicHelper::or(null));

    }

    public function testAnd(): void
    {
        $this->assertFalse(TrinaryLogicHelper::and(false, false));
        $this->assertFalse(TrinaryLogicHelper::and(false, null));
        $this->assertFalse(TrinaryLogicHelper::and(false, true));
        $this->assertFalse(TrinaryLogicHelper::and(null, false));
        $this->assertNull(TrinaryLogicHelper::and(null, null));
        $this->assertNull(TrinaryLogicHelper::and(null, true));
        $this->assertFalse(TrinaryLogicHelper::and(true, false));
        $this->assertNull(TrinaryLogicHelper::and(true, null));
        $this->assertTrue(TrinaryLogicHelper::and(true, true));


        //Should work for longer arrays too
        $this->assertFalse(TrinaryLogicHelper::and(true, true, false, null));
        $this->assertFalse(TrinaryLogicHelper::and(false, false, false, false, null));
        $this->assertFalse(TrinaryLogicHelper::and(false, false, false));
        $this->assertNull(TrinaryLogicHelper::and(true, true, null));
        $this->assertTrue(TrinaryLogicHelper::and(true, true, true));

        //Test for one argument
        $this->assertTrue(TrinaryLogicHelper::and(true));
        $this->assertFalse(TrinaryLogicHelper::and(false));
        $this->assertNull(TrinaryLogicHelper::and(null));
 }
}
