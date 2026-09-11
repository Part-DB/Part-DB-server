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
namespace App\Services\LogSystem;

use App\Entity\LogSystem\AccessMethod;
use App\Services\Misc\ConsoleInfoHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Determines the access method (WebUI/CLI/REST API/MCP) of the current request or console command,
 * and provides a stable UUID identifying it, so that all log entries created during the same request
 * or console command execution can be grouped together.
 */
class RequestContextHelper implements ResetInterface
{
    private ?Uuid $requestId = null;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ConsoleInfoHelper $consoleInfoHelper,
    ) {
    }

    /**
     * Determines the access method that was used for the current request or console command.
     */
    public function getAccessMethod(): AccessMethod
    {
        if ($this->consoleInfoHelper->isCLI()) {
            return AccessMethod::CLI;
        }

        $request = $this->requestStack->getMainRequest();
        if (!$request instanceof Request) {
            return AccessMethod::WEB;
        }

        $path = $request->getPathInfo();

        if (str_starts_with($path, '/mcp')) {
            return AccessMethod::MCP;
        }

        if (str_starts_with($path, '/api') || str_starts_with($path, '/kicad-api')) {
            return AccessMethod::API;
        }

        return AccessMethod::WEB;
    }

    /**
     * Returns a UUID identifying the current request or console command execution.
     * The same UUID is returned for every call during the same request/command, so it can be used to
     * group all log entries created by it.
     */
    public function getRequestId(): Uuid
    {
        return $this->requestId ??= Uuid::v7();
    }

    /**
     * Resets the cached request ID. Called automatically by the framework at the end of every request
     * (including under persistent worker runtimes like FrankenPHP, where services are not recreated
     * between requests), so that a fresh ID is generated for the next request.
     */
    public function reset(): void
    {
        $this->requestId = null;
    }
}
