<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2025 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Tests\Services\InfoProviderSystem\DTOs;

use App\Services\InfoProviderSystem\DTOs\BulkSearchFieldMappingDTO;
use PHPUnit\Framework\TestCase;

class BulkSearchFieldMappingDTOTest extends TestCase
{

    public function testIsSupplierPartNumberField(): void
    {
        $fieldMapping = new BulkSearchFieldMappingDTO(field: 'reichelt_spn', providers: ['provider1'], priority: 1);
        $this->assertTrue($fieldMapping->isSupplierPartNumberField());

        $fieldMapping = new BulkSearchFieldMappingDTO(field: 'partNumber', providers: ['provider1'], priority: 1);
        $this->assertFalse($fieldMapping->isSupplierPartNumberField());
    }

    public function testToSerializableArray(): void
    {
        $fieldMapping = new BulkSearchFieldMappingDTO(field: 'test', providers: ['provider1', 'provider2'], priority: 3);
        $array = $fieldMapping->toSerializableArray();
        $this->assertIsArray($array);
        $this->assertSame([
            'field' => 'test',
            'providers' => ['provider1', 'provider2'],
            'priority' => 3,
        ], $array);
    }

    public function testFromSerializableArray(): void
    {
        $data = [
            'field' => 'test',
            'providers' => ['provider1', 'provider2'],
            'priority' => 3,
        ];
        $fieldMapping = BulkSearchFieldMappingDTO::fromSerializableArray($data);
        $this->assertInstanceOf(BulkSearchFieldMappingDTO::class, $fieldMapping);
        $this->assertSame('test', $fieldMapping->field);
        $this->assertSame(['provider1', 'provider2'], $fieldMapping->providers);
        $this->assertSame(3, $fieldMapping->priority);
    }
}
