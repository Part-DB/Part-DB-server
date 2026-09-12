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

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use App\Entity\Parts\Part;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Any MCP tool on the Part resource that declares itself read-only
 * (`annotations: ['readOnlyHint' => true, ...]`) must be gated with a read
 * permission expression (e.g. `is_granted("@parts.read")`), never with a
 * mutating permission like `edit`, `create` or `delete`.
 *
 * Background: ApiPlatform/MCP enforces each tool's `security` expression on
 * `tools/call` without a subject object bound, so an expression such as
 * `is_granted("edit", object)` can never resolve the Part and always denies
 * with "Access Denied" — even for users with full Part permissions. The
 * per-object read check already happens inside the tool's processor (e.g.
 * GetPartByIdProcessor), so the declared security must only require the
 * collection-level read permission, just like the sibling read-only tools.
 *
 * This is a regression test for exactly that bug (get_part_details was
 * declared with the update_part mutation expression): it inspects the
 * registered ApiPlatform metadata for the Part resource (the same metadata
 * the MCP server enforces), not just what the processor checks in isolation —
 * unit/functional tests calling the processor directly cannot catch this
 * class of bug.
 */
class ReadOnlyMcpToolsUseReadSecurityTest extends KernelTestCase
{
    /**
     * @return array<string, object>
     */
    private static function getPartMcpTools(): array
    {
        self::bootKernel();
        $factory = self::getContainer()->get(ResourceMetadataCollectionFactoryInterface::class);
        $collection = $factory->create(Part::class);

        $tools = null;
        foreach ($collection as $resource) {
            if (null !== ($mcp = $resource->getMcp())) {
                $tools = $mcp;
                break;
            }
        }
        self::assertNotNull($tools, 'The Part resource should expose MCP tools');

        return $tools;
    }

    public function testReadOnlyToolsDoNotRequireMutatingPermission(): void
    {
        $tools = self::getPartMcpTools();

        $readOnlyTools = [];
        foreach ($tools as $name => $tool) {
            $annotations = $tool->getAnnotations();
            if (\is_array($annotations) && ($annotations['readOnlyHint'] ?? null) === true) {
                $readOnlyTools[$name] = $tool;
            }
        }

        self::assertNotEmpty($readOnlyTools, 'The Part resource should expose read-only MCP tools');

        foreach ($readOnlyTools as $name => $tool) {
            $security = $tool->getSecurity() ?? '';
            foreach (['edit', 'create', 'delete'] as $forbidden) {
                self::assertStringNotContainsStringIgnoringCase(
                    $forbidden,
                    $security,
                    sprintf(
                        'Read-only MCP tool "%s" must not require "%s" permission (security: "%s"). '
                        .'Read-only tools are enforced without a subject object, so only a read gate such as '
                        .'is_granted("@parts.read") works here; the per-object check lives in the processor.',
                        $name,
                        $forbidden,
                        $security
                    )
                );
            }
        }
    }

    public function testGetPartDetailsUsesReadSecurity(): void
    {
        $tools = self::getPartMcpTools();

        self::assertArrayHasKey('get_part_details', $tools, 'The Part resource should expose a get_part_details MCP tool');
        self::assertSame(
            'is_granted("@parts.read")',
            $tools['get_part_details']->getSecurity(),
            'get_part_details is read-only and must stay aligned with its sibling read-only tools '
            .'(search_parts, advanced_search_parts, get_part_preview_image).'
        );
    }
}
