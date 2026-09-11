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

namespace App\Tests\Services\Cache;

use App\Entity\Parts\Part;
use App\Services\Cache\ElementCacheTagGenerator;
use PHPUnit\Framework\TestCase;

final class ElementCacheTagGeneratorTest extends TestCase
{
    private ElementCacheTagGenerator $service;

    protected function setUp(): void
    {
        $this->service = new ElementCacheTagGenerator();
    }

    public function testClassNameIsConvertedToTag(): void
    {
        $tag = $this->service->getElementTypeCacheTag(Part::class);
        // Backslashes must be replaced by underscores
        $this->assertStringNotContainsString('\\', $tag);
        $this->assertSame(str_replace('\\', '_', Part::class), $tag);
    }

    public function testObjectInputGivesSameResultAsClassName(): void
    {
        $part = new Part();
        $tagFromObject = $this->service->getElementTypeCacheTag($part);
        $tagFromClass = $this->service->getElementTypeCacheTag(Part::class);
        $this->assertSame($tagFromClass, $tagFromObject);
    }

    public function testResultIsCached(): void
    {
        $tag1 = $this->service->getElementTypeCacheTag(Part::class);
        $tag2 = $this->service->getElementTypeCacheTag(Part::class);
        $this->assertSame($tag1, $tag2);
    }

    public function testNonExistentClassThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->getElementTypeCacheTag('App\\NonExistent\\Foo');
    }
}
