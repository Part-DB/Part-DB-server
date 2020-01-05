<?php

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Tests\Services;

use App\Entity\Attachments\PartAttachment;
use App\Entity\Base\DBElement;
use App\Entity\Base\NamedDBElement;
use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use App\Exceptions\EntityNotSupportedException;
use App\Services\AmountFormatter;
use App\Services\ElementTypeNameGenerator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ElementTypeNameGeneratorTest extends WebTestCase
{
    /**
     * @var AmountFormatter
     */
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();

        //Get an service instance.
        self::bootKernel();
        $this->service = self::$container->get(ElementTypeNameGenerator::class);
    }

    public function testGetLocalizedTypeNameCombination(): void
    {
        //We only test in english
        $this->assertSame('Part', $this->service->getLocalizedTypeLabel(new Part()));
        $this->assertSame('Category', $this->service->getLocalizedTypeLabel(new Category()));

        //Test inheritance
        $this->assertSame('Attachment', $this->service->getLocalizedTypeLabel(new PartAttachment()));

        //Test exception for unknpwn type
        $this->expectException(EntityNotSupportedException::class);
        $this->service->getLocalizedTypeLabel(new class() extends DBElement {
            public function getIDString(): string
            {
                return 'Stub';
            }
        });
    }

    public function testGetTypeNameCombination(): void
    {
        $part = new Part();
        $part->setName('Test<Part');
        //When the text version is used, dont escape the name
        $this->assertSame('Part: Test<Part', $this->service->getTypeNameCombination($part, false));

        $this->assertSame('<i>Part:</i> Test&lt;Part', $this->service->getTypeNameCombination($part, true));

        //Test exception
        $this->expectException(EntityNotSupportedException::class);
        $this->service->getTypeNameCombination(new class() extends NamedDBElement {
            public function getIDString(): string
            {
                return 'Stub';
            }
        });
    }
}
