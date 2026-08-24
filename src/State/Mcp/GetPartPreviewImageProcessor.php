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
use App\Entity\Attachments\Attachment;
use App\Entity\Parts\Part;
use App\Mcp\DTO\ElementByIdInput;
use App\Services\Attachments\AttachmentManager;
use App\Services\Attachments\PartPreviewGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Schema\Content\ImageContent;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Returns the preview/thumbnail picture for a part as an MCP image content block. The attachment to use is
 * determined by {@see PartPreviewGenerator::getTablePreviewAttachment()} — the same service the part list/table
 * in the web UI uses — so the part -> footprint -> built project fallback chain lives in exactly one place.
 */
final readonly class GetPartPreviewImageProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AuthorizationCheckerInterface $authorizationChecker,
        private PartPreviewGenerator $partPreviewGenerator,
        private AttachmentManager $attachmentManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CallToolResult
    {
        if (!$data instanceof ElementByIdInput) {
            throw new BadRequestHttpException('Expected ElementByIdInput');
        }

        $part = $this->entityManager->find(Part::class, $data->id);

        if (!$part instanceof Part) {
            throw new NotFoundHttpException(sprintf('Part with id %d not found', $data->id));
        }

        if (!$this->authorizationChecker->isGranted('read', $part)) {
            throw new AccessDeniedException(sprintf('Access denied to part with id %d', $data->id));
        }

        $attachment = $this->partPreviewGenerator->getTablePreviewAttachment($part);

        if (!$attachment instanceof Attachment) {
            return CallToolResult::success([
                new TextContent(sprintf('Part with id %d (nor its footprint or built project) has no preview picture available.', $data->id)),
            ]);
        }

        //The preview attachment might come from the part's footprint or built project, which the current user
        //might not have explicit read access to, so we re-check permissions on the attachment itself here.
        if (!$this->authorizationChecker->isGranted('read', $attachment)) {
            throw new AccessDeniedException(sprintf('Access denied to the preview picture of part with id %d', $data->id));
        }

        if ($attachment->isSecure() && !$this->authorizationChecker->isGranted('show_private', $attachment)) {
            throw new AccessDeniedException(sprintf('Access denied to the private preview picture of part with id %d', $data->id));
        }

        if ($attachment->hasInternal() && $this->attachmentManager->isInternalFileExisting($attachment)) {
            $path = $this->attachmentManager->toAbsoluteInternalFilePath($attachment);
            if (null !== $path) {
                return CallToolResult::success([ImageContent::fromFile($path)]);
            }
        }

        if ($attachment->hasExternal()) {
            return CallToolResult::success([
                new TextContent(sprintf('The preview picture of part with id %d is hosted externally: %s', $data->id, $attachment->getExternalPath())),
            ]);
        }

        return CallToolResult::success([
            new TextContent(sprintf('Part with id %d has no usable preview picture available.', $data->id)),
        ]);
    }
}
