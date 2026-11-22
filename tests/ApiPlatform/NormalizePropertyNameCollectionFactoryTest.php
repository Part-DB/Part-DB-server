<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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
namespace App\Tests\ApiPlatform;

use ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Property\PropertyNameCollection;
use App\ApiPlatform\NormalizePropertyNameCollectionFactory;
use App\Entity\Parts\Part;
use PHPUnit\Framework\TestCase;

final class NormalizePropertyNameCollectionFactoryTest extends TestCase
{
    public function testCamelCaseDuplicatesAreRemovedIgnoringCase(): void
    {
        //Regression guard for https://github.com/Part-DB/Part-DB-server/issues/1414: getManufacturerProductUrl() and
        //setManufacturerProductURL() both have to be dropped in favour of manufacturer_product_url
        $decorated = $this->createStub(PropertyNameCollectionFactoryInterface::class);
        $decorated->method('create')->willReturn(new PropertyNameCollection(
            ['name', 'manufacturer_product_url', 'manufacturerProductUrl', 'manufacturerProductURL']
        ));

        $factory = new NormalizePropertyNameCollectionFactory($decorated);
        $result = $factory->create(Part::class, ['schema_type' => 'output']);

        $this->assertSame(['name', 'manufacturer_product_url'], array_values(iterator_to_array($result)));
    }
}
