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

namespace App\Tests\Serializer;

use App\Mcp\DTO\StructuralElementOverview;
use App\Serializer\StructuralElementOverviewNormalizer;
use PHPUnit\Framework\TestCase;

final class StructuralElementOverviewNormalizerTest extends TestCase
{
    private StructuralElementOverviewNormalizer $service;

    protected function setUp(): void
    {
        $this->service = new StructuralElementOverviewNormalizer();
    }

    public function testNormalize(): void
    {
        $overview = new StructuralElementOverview(id: 42, name: 'Test Node');

        //Must be a plain array with exactly id+name, no JSON-LD "@id"/"@type" clutter
        $this->assertSame(['id' => 42, 'name' => 'Test Node'], $this->service->normalize($overview));
    }

    public function testSupportsNormalization(): void
    {
        $this->assertFalse($this->service->supportsNormalization(new \stdClass()));
        $this->assertTrue($this->service->supportsNormalization(new StructuralElementOverview(id: 1, name: 'X')));
    }
}
