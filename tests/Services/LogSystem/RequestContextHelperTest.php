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
namespace App\Tests\Services\LogSystem;

use App\Entity\LogSystem\AccessMethod;
use App\Services\LogSystem\RequestContextHelper;
use App\Services\Misc\ConsoleInfoHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class RequestContextHelperTest extends TestCase
{
    private function getHelper(?string $path, bool $isCLI = false): RequestContextHelper
    {
        $requestStack = new RequestStack();
        if (null !== $path) {
            $request = Request::create($path);
            $requestStack->push($request);
        }

        $consoleInfoHelper = $this->createMock(ConsoleInfoHelper::class);
        $consoleInfoHelper->method('isCLI')->willReturn($isCLI);

        return new RequestContextHelper($requestStack, $consoleInfoHelper);
    }

    public function testGetAccessMethodCLI(): void
    {
        $helper = $this->getHelper('/some/path', isCLI: true);
        $this->assertSame(AccessMethod::CLI, $helper->getAccessMethod());
    }

    public function testGetAccessMethodMCP(): void
    {
        $helper = $this->getHelper('/mcp/some-tool');
        $this->assertSame(AccessMethod::MCP, $helper->getAccessMethod());
    }

    public function testGetAccessMethodAPI(): void
    {
        $helper = $this->getHelper('/api/parts');
        $this->assertSame(AccessMethod::API, $helper->getAccessMethod());

        $helper = $this->getHelper('/kicad-api/v1/parts');
        $this->assertSame(AccessMethod::API, $helper->getAccessMethod());
    }

    public function testGetAccessMethodWeb(): void
    {
        $helper = $this->getHelper('/en/part/1');
        $this->assertSame(AccessMethod::WEB, $helper->getAccessMethod());
    }

    public function testGetAccessMethodWebWithoutRequest(): void
    {
        //No main request available (e.g. no request pushed onto the stack) falls back to WEB
        $helper = $this->getHelper(null);
        $this->assertSame(AccessMethod::WEB, $helper->getAccessMethod());
    }

    public function testGetRequestIdIsStable(): void
    {
        $helper = $this->getHelper('/en/part/1');

        $id1 = $helper->getRequestId();
        $id2 = $helper->getRequestId();

        $this->assertTrue($id1->equals($id2));
    }

    public function testResetGeneratesNewRequestId(): void
    {
        $helper = $this->getHelper('/en/part/1');

        $id1 = $helper->getRequestId();
        $helper->reset();
        $id2 = $helper->getRequestId();

        $this->assertFalse($id1->equals($id2));
    }
}
