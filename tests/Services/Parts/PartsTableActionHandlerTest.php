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
namespace App\Tests\Services\Parts;

use App\Entity\Parts\Part;
use App\Services\Parts\PartsTableActionHandler;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;

class PartsTableActionHandlerTest extends WebTestCase
{
    private PartsTableActionHandler $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(PartsTableActionHandler::class);
    }

    public function testExportActionsRedirectToExportController(): void
    {
        // Mock a Part entity with required properties
        $part = $this->createMock(Part::class);
        $part->method('getId')->willReturn(1);
        $part->method('getName')->willReturn('Test Part');
        
        $selected_parts = [$part];

        // Test each export format, focusing on our new xlsx format
        $formats = ['json', 'csv', 'xml', 'yaml', 'xlsx'];
        
        foreach ($formats as $format) {
            $action = "export_{$format}";
            $result = $this->service->handleAction($action, $selected_parts, 1, '/test');
            
            $this->assertInstanceOf(RedirectResponse::class, $result);
            $this->assertStringContainsString('parts/export', $result->getTargetUrl());
            $this->assertStringContainsString("format={$format}", $result->getTargetUrl());
        }
    }

    public function testIdStringToArray(): void
    {
        // This test would require actual Part entities in the database
        // For now, we just test the method exists and handles empty strings
        $result = $this->service->idStringToArray('');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}