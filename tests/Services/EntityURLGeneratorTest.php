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

namespace App\Tests\Services;

use App\Entity\Base\AbstractDBElement;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\Part;
use App\Entity\Parts\StorageLocation;
use App\Entity\Parts\Supplier;
use App\Entity\UserSystem\User;
use App\Exceptions\EntityNotSupportedException;
use App\Services\EntityURLGenerator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class EntityURLGeneratorTest extends WebTestCase
{
    private static EntityURLGenerator $service;

    public static function setUpBeforeClass(): void
    {
        self::bootKernel();
        self::$service = self::getContainer()->get(EntityURLGenerator::class);
    }

    private function entityWithId(string $class, int $id): AbstractDBElement
    {
        $entity = new $class();
        $ref = new \ReflectionProperty(AbstractDBElement::class, 'id');
        $ref->setValue($entity, $id);
        return $entity;
    }

    public function testInfoUrlForPartContainsPartPath(): void
    {
        $part = $this->entityWithId(Part::class, 1);
        $url = self::$service->infoURL($part);
        $this->assertStringContainsString('part', $url);
        $this->assertStringContainsString('1', $url);
    }

    public function testEditUrlForCategoryContainsCategoryPath(): void
    {
        $category = $this->entityWithId(Category::class, 5);
        $url = self::$service->editURL($category);
        $this->assertStringContainsString('category', $url);
        $this->assertStringContainsString('5', $url);
    }

    public function testListPartsUrlForSupplierContainsSupplierPath(): void
    {
        $supplier = $this->entityWithId(Supplier::class, 7);
        $url = self::$service->listPartsURL($supplier);
        $this->assertStringContainsString('supplier', $url);
    }

    public function testGetUrlWithInfoTypeCallsInfoUrl(): void
    {
        $part = $this->entityWithId(Part::class, 3);
        $url = self::$service->getURL($part, 'info');
        $this->assertStringContainsString('part', $url);
    }

    public function testGetUrlWithEditTypeCallsEditUrl(): void
    {
        $manufacturer = $this->entityWithId(Manufacturer::class, 2);
        $url = self::$service->getURL($manufacturer, 'edit');
        $this->assertStringContainsString('manufacturer', $url);
    }

    public function testGetUrlWithUnknownTypeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $part = $this->entityWithId(Part::class, 1);
        self::$service->getURL($part, 'unsupported_type');
    }

    public function testInfoUrlForUserContainsUserPath(): void
    {
        $user = $this->entityWithId(User::class, 10);
        $url = self::$service->editURL($user);
        $this->assertStringContainsString('user', $url);
    }

    public function testListPartsUrlForStorelocationContainsStorelocationPath(): void
    {
        $loc = $this->entityWithId(StorageLocation::class, 4);
        $url = self::$service->listPartsURL($loc);
        $this->assertStringContainsString('store', $url);
    }
}
