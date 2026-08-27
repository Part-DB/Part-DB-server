<?php
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

declare(strict_types=1);

namespace App\State\Mcp;

use ApiPlatform\Validator\Exception\ValidationException;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * By default, ANY exception thrown by an MCP tool's processor (permission denied, not-found, bad input,
 * validation failure, ...) is caught by the MCP SDK's transport layer and turned into a generic, message-less
 * JSON-RPC "Internal error" (-32603) - the client (and the AI agent using it) sees no explanation whatsoever,
 * and nothing is logged server-side either, since that catch happens above Symfony's own exception/logging
 * pipeline. The MCP spec's own recommendation (see the CallToolResult class docblock) is the opposite: expected,
 * "the tool failed for a reason the caller can act on" errors should be reported as a normal, successful
 * CallToolResult with isError:true and an explanatory text, not as a protocol-level error - that way the calling
 * AI model actually sees *why* the call failed and can decide how to proceed (e.g. tell the user their token
 * needs a higher permission scope), instead of just seeing an opaque error code.
 *
 * Every write MCP processor in this namespace wraps its process() body with runCatchingExpectedErrors() so that
 * these expected failure modes are reported this way. Anything NOT caught here (a genuine bug, a TypeError, ...)
 * is deliberately left to propagate and fall back to the generic internal-error path, so it still surfaces during
 * development/testing rather than being silently swallowed as a false "tool error".
 */
trait McpToolErrorHandling
{
    /**
     * @template T
     * @param callable(): T $fn
     * @return T|CallToolResult
     */
    private function runCatchingExpectedErrors(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (AccessDeniedException|NotFoundHttpException|BadRequestHttpException|ValidationException|\LogicException $e) {
            return new CallToolResult([new TextContent($e->getMessage())], isError: true);
        }
    }
}
