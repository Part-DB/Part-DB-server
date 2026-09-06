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
namespace App\Tests\Entity\LogSystem;

use App\Entity\LogSystem\AccessMethod;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AccessMethodTest extends TestCase
{
    public function testImplementsTranslatableInterface(): void
    {
        $this->assertInstanceOf(TranslatableInterface::class, AccessMethod::WEB);
    }

    public function testTransUsesExpectedKeyPerCase(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->exactly(4))
            ->method('trans')
            ->willReturnMap([
                ['log.access_method.web', [], null, null, 'Web UI'],
                ['log.access_method.cli', [], null, null, 'CLI'],
                ['log.access_method.api', [], null, null, 'REST API'],
                ['log.access_method.mcp', [], null, null, 'MCP'],
            ]);

        $this->assertSame('Web UI', AccessMethod::WEB->trans($translator));
        $this->assertSame('CLI', AccessMethod::CLI->trans($translator));
        $this->assertSame('REST API', AccessMethod::API->trans($translator));
        $this->assertSame('MCP', AccessMethod::MCP->trans($translator));
    }

    public function testGetIconClassIsUniquePerCase(): void
    {
        $icons = array_map(static fn(AccessMethod $m) => $m->getIconClass(), AccessMethod::cases());

        //Every case must have a non-empty icon class, and they must all be distinct
        foreach ($icons as $icon) {
            $this->assertNotSame('', $icon);
        }
        $this->assertCount(count(AccessMethod::cases()), array_unique($icons));
    }
}
