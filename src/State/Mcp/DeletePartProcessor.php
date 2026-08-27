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

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Parts\Part;
use App\Mcp\DTO\DeletePartInput;
use App\Services\LogSystem\EventCommentHelper;
use App\Settings\AISettings\McpSettings;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Permanently deletes a part by its database ID, mirroring PartController::delete(). Returns a plain text
 * confirmation via CallToolResult rather than the (now nonexistent) part - there is nothing meaningful left to
 * normalize through the usual part:read groups once the entity has been removed.
 */
final class DeletePartProcessor implements ProcessorInterface
{
    use McpToolErrorHandling;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly EventCommentHelper $eventCommentHelper,
        private readonly McpSettings $mcpSettings,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CallToolResult
    {
        return $this->runCatchingExpectedErrors(function () use ($data) {
            $this->mcpSettings->assertEditingEnabled();

            return $this->deletePart($data);
        });
    }

    private function deletePart(mixed $data): CallToolResult
    {
        if (!$data instanceof DeletePartInput) {
            throw new \InvalidArgumentException('Expected DeletePartInput');
        }

        $part = $this->entityManager->find(Part::class, $data->id);
        if (!$part instanceof Part) {
            throw new NotFoundHttpException(sprintf('Part with id %d not found.', $data->id));
        }

        //Manual check - the McpTool's `security` attribute is not enforced by the MCP call pipeline (see Part.php)
        if (!$this->authorizationChecker->isGranted('delete', $part)) {
            throw new AccessDeniedException(sprintf('Access denied to delete part with id %d.', $data->id));
        }

        $name = $part->getName();
        $id = $part->getID();

        if ($data->logComment !== null && $data->logComment !== '' && !$this->eventCommentHelper->isMessageSet()) {
            $this->eventCommentHelper->setMessage($data->logComment);
        }

        $this->entityManager->remove($part);
        $this->entityManager->flush();

        return CallToolResult::success([new TextContent(sprintf('Part "%s" (id %d) has been deleted.', $name, $id))]);
    }
}
