<?php

declare(strict_types=1);

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
namespace App\Tests\Entity\Parts;

use App\Entity\Parts\AssociationType;
use App\Entity\Parts\PartAssociation;
use PHPUnit\Framework\TestCase;

class PartAssociationTest extends TestCase
{

    public function testGetTypeTranslationKey(): void
    {
        $assoc = new PartAssociation();
        $assoc->setType(AssociationType::COMPATIBLE);
        $assoc->setOtherType('Custom Type');

        //If the type is not OTHER the translation key should be the same as the type
        $this->assertSame($assoc->getType()->getTranslationKey(), $assoc->getTypeTranslationKey());

        //If the type is OTHER the translation key should be the other type
        $assoc->setType(AssociationType::OTHER);
        $this->assertEquals($assoc->getOtherType(), $assoc->getTypeTranslationKey());
    }
}
