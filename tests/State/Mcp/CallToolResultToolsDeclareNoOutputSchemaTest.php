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

namespace App\Tests\State\Mcp;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Any MCP tool whose processor returns a raw `Mcp\Schema\Result\CallToolResult` (instead of letting
 * ApiPlatform\Mcp\State\StructuredContentProcessor normalize the return value) must also declare
 * `structuredContent: false` on its McpTool attribute. Otherwise ApiPlatform\Mcp\Capability\Registry\Loader
 * still builds and advertises an outputSchema derived from the resource's own normal read schema, which real
 * MCP clients then validate against the response and reject, since a CallToolResult never populates
 * `structuredContent` (only the normal serialize path does).
 *
 * This is a regression test for exactly that bug: it inspects the schema actually registered with the MCP
 * server (via the real Registry/Loader pipeline, same as `debug:mcp` does), not just what the processor
 * returns in isolation — unit/functional tests calling the processor directly cannot catch this class of bug.
 */
class CallToolResultToolsDeclareNoOutputSchemaTest extends KernelTestCase
{
    public static function provideCallToolResultToolNames(): array
    {
        return [
            'get_attachment_content' => ['get_attachment_content'],
            'get_part_preview_image' => ['get_part_preview_image'],
        ];
    }

    #[DataProvider('provideCallToolResultToolNames')]
    public function testToolDeclaresNoOutputSchema(string $toolName): void
    {
        self::bootKernel();
        $container = self::getContainer();

        //Service ids, not autowired by class (see vendor/symfony/mcp-bundle/config/services.php)
        $container->get('mcp.server.builder')->build();
        $registry = $container->get('mcp.registry');

        $tool = $registry->getTool($toolName)->tool;

        self::assertNull(
            $tool->outputSchema,
            sprintf(
                'Tool "%s" declares an output schema, but its processor returns a raw CallToolResult and never '.
                'populates structuredContent — real MCP clients will reject every call with "declares an output '.
                'schema but returned no structured content". Add structuredContent: false to its McpTool attribute.',
                $toolName
            )
        );
    }
}
